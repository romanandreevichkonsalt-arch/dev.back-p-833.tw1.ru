<?php

namespace app\services\import\fabric;

use app\models\CatalogFabricCollection;
use app\models\CatalogFabricColor;
use app\models\CatalogImportMediaQueue;
use app\models\CatalogImportRun;
use app\models\MediaFile;
use app\services\media\MediaContentValidator;
use Yii;

final class FabricImportMediaQueueProcessor
{
    private const DEFAULT_BATCH = 80;
    private const DEFAULT_CONCURRENCY = 8;

    public function __construct(
        private readonly MediaImportService $mediaImportService = new MediaImportService(),
        private readonly FabricMediaBatchDownloader $batchDownloader = new FabricMediaBatchDownloader(),
        private readonly FabricImportMediaDedup $dedup = new FabricImportMediaDedup(),
    ) {
    }

    /**
     * @return array{processed: int, failed: int, pending: int, continue: bool}
     */
    public function processRun(
        int $runId,
        int $limit = self::DEFAULT_BATCH,
        int $concurrency = self::DEFAULT_CONCURRENCY,
    ): array {
        @set_time_limit(0);

        $run = CatalogImportRun::findOne(['id' => $runId, 'type' => CatalogImportRun::TYPE_FABRIC]);
        if ($run === null) {
            return ['processed' => 0, 'failed' => 0, 'pending' => 0, 'continue' => false];
        }

        $items = CatalogImportMediaQueue::find()
            ->where([
                'import_run_id' => $runId,
                'status' => CatalogImportMediaQueue::STATUS_PENDING,
                'entity_type' => 'fabric',
            ])
            ->andWhere(['not', ['entity_id' => null]])
            ->orderBy(['id' => SORT_ASC])
            ->limit(max(1, $limit))
            ->all();

        if ($items === []) {
            $pending = $this->countPending($runId);

            return ['processed' => 0, 'failed' => 0, 'pending' => $pending, 'continue' => false];
        }

        $processed = 0;
        $failed = 0;
        $downloadJobs = [];
        /** @var array<int, CatalogImportMediaQueue> $itemsById */
        $itemsById = [];

        foreach ($items as $item) {
            $itemsById[(int)$item->id] = $item;
            $fingerprint = trim((string)($item->url_fingerprint ?? ''));
            $reusedId = $fingerprint !== '' ? $this->dedup->findMediaIdByFingerprint($fingerprint) : null;
            if ($reusedId !== null && $this->attachMediaToItem($item, $reusedId)) {
                $processed++;
                $this->propagateFingerprintSuccess($runId, $fingerprint, $reusedId);
                continue;
            }

            $url = trim((string)$item->source_url);
            if ($url === '') {
                $this->markFailed($item, 'Пустой URL.');
                $failed++;
                continue;
            }

            $downloadJobs[] = [
                'id' => (int)$item->id,
                'url' => $url,
                'kind' => $this->mediaImportService->resolveFabricTextureKind($url),
            ];
        }

        if ($downloadJobs !== []) {
            $downloads = $this->batchDownloader->downloadParallel($downloadJobs, $concurrency);

            foreach ($downloadJobs as $job) {
                $item = $itemsById[$job['id']] ?? null;
                if ($item === null) {
                    continue;
                }

                $download = $downloads[$job['id']] ?? FabricMediaDownloadResult::failed('Нет ответа загрузчика');

                if ($this->processDownloadResult($item, $download, $runId)) {
                    $processed++;
                } else {
                    $failed++;
                }
            }
        }

        $this->updateRunMediaProgress($run, $failed);

        $pending = $this->countPending($runId);

        return [
            'processed' => $processed,
            'failed' => $failed,
            'pending' => $pending,
            'continue' => $pending > 0,
        ];
    }

    private function processDownloadResult(
        CatalogImportMediaQueue $item,
        FabricMediaDownloadResult $download,
        int $runId,
    ): bool {
        if (!$download->success || $download->tempPath === null) {
            return $this->fallbackSingleDownload($item, $download->errorMessage ?? 'Ошибка загрузки');
        }

        $link = CatalogFabricColor::findOne((int)$item->entity_id);
        if ($link === null) {
            @unlink($download->tempPath);
            $this->markFailed($item, 'Связь коллекция-цвет не найдена.');

            return false;
        }

        $collection = CatalogFabricCollection::findOne((int)$link->fabric_collection_id);
        if ($collection === null) {
            @unlink($download->tempPath);
            $this->markFailed($item, 'Коллекция не найдена.');

            return false;
        }

        $url = trim((string)$item->source_url);

        try {
            $media = $this->mediaImportService->importFabricTextureFromDownloadedFile(
                $download->tempPath,
                $url,
                (string)$collection->slug,
                FabricDesignCodeNormalizer::normalize((string)$link->design_code),
                $download->downloadedFilename,
                false,
            );
        } catch (\Throwable $exception) {
            @unlink($download->tempPath);

            return $this->fallbackSingleDownload($item, $exception->getMessage());
        } finally {
            if (is_file($download->tempPath)) {
                @unlink($download->tempPath);
            }
        }

        return $this->finalizeItemWithMedia($item, $link, $media, $runId);
    }

    private function fallbackSingleDownload(CatalogImportMediaQueue $item, string $reason): bool
    {
        $link = CatalogFabricColor::findOne((int)$item->entity_id);
        if ($link === null) {
            $this->markFailed($item, $reason);

            return false;
        }

        $collection = CatalogFabricCollection::findOne((int)$link->fabric_collection_id);
        if ($collection === null) {
            $this->markFailed($item, $reason);

            return false;
        }

        $url = trim((string)$item->source_url);
        if ($url === '') {
            $this->markFailed($item, $reason);

            return false;
        }

        try {
            $media = $this->mediaImportService->importFabricTextureWithRetry(
                $url,
                (string)$collection->slug,
                FabricDesignCodeNormalizer::normalize((string)$link->design_code),
                false,
            );
            if ($media === null) {
                $this->markFailed($item, $reason);

                return false;
            }

            return $this->finalizeItemWithMedia($item, $link, $media, (int)$item->import_run_id);
        } catch (\Throwable $exception) {
            $this->markFailed($item, $reason . ' | ' . $exception->getMessage());

            return false;
        }
    }

    private function finalizeItemWithMedia(
        CatalogImportMediaQueue $item,
        CatalogFabricColor $link,
        MediaFile $media,
        int $runId,
    ): bool {
        if (!$this->attachMediaToItem($item, (int)$media->id, $link)) {
            return false;
        }

        $fingerprint = trim((string)($item->url_fingerprint ?? ''));
        if ($fingerprint !== '') {
            $this->propagateFingerprintSuccess($runId, $fingerprint, (int)$media->id);
        }

        if (
            $media->isImage()
            && !MediaContentValidator::isVariantFileReadable($media, 'medium')
        ) {
            FabricImportVariantMediaRegistry::registerForRun($runId, $media);
        }

        return true;
    }

    private function attachMediaToItem(
        CatalogImportMediaQueue $item,
        int $mediaId,
        ?CatalogFabricColor $link = null,
    ): bool {
        $link ??= CatalogFabricColor::findOne((int)$item->entity_id);
        if ($link === null) {
            $this->markFailed($item, 'Связь коллекция-цвет не найдена.');

            return false;
        }

        $media = MediaFile::findOne($mediaId);
        if ($media === null) {
            $this->markFailed($item, 'MediaFile #' . $mediaId . ' не найден.');

            return false;
        }

        if ($media->kind === MediaFile::KIND_IMAGE) {
            $link->swatch_media_id = $mediaId;
        } else {
            $link->pbr_media_id = $mediaId;
        }
        if ($link->hasAttribute('import_media_pending')) {
            $link->import_media_pending = false;
        }
        if ($link->hasAttribute('import_media_fingerprint') && trim((string)($item->url_fingerprint ?? '')) !== '') {
            $link->import_media_fingerprint = (string)$item->url_fingerprint;
        }
        $link->save(false);

        $item->status = CatalogImportMediaQueue::STATUS_DONE;
        $item->media_file_id = $mediaId;
        $item->error_message = null;
        $item->save(false);

        return true;
    }

    private function propagateFingerprintSuccess(int $runId, string $fingerprint, int $mediaId): void
    {
        if ($fingerprint === '') {
            return;
        }

        $duplicates = CatalogImportMediaQueue::find()
            ->where([
                'import_run_id' => $runId,
                'url_fingerprint' => $fingerprint,
                'status' => CatalogImportMediaQueue::STATUS_PENDING,
            ])
            ->all();

        foreach ($duplicates as $duplicate) {
            $this->attachMediaToItem($duplicate, $mediaId);
        }
    }

    private function markFailed(CatalogImportMediaQueue $item, string $message): void
    {
        $item->status = CatalogImportMediaQueue::STATUS_FAILED;
        $item->error_message = mb_substr($message, 0, 2000);
        $item->save(false);

        if ($item->entity_id !== null && (int)$item->entity_id > 0) {
            $link = CatalogFabricColor::findOne((int)$item->entity_id);
            if ($link !== null && $link->hasAttribute('import_media_pending')) {
                $link->import_media_pending = false;
                $link->save(false, ['import_media_pending']);
            }
        }
    }

    private function countPending(int $runId): int
    {
        return (int)CatalogImportMediaQueue::find()
            ->where([
                'import_run_id' => $runId,
                'status' => CatalogImportMediaQueue::STATUS_PENDING,
                'entity_type' => 'fabric',
            ])
            ->count();
    }

    private function updateRunMediaProgress(CatalogImportRun $run, int $batchFailed): void
    {
        $total = (int)CatalogImportMediaQueue::find()
            ->where(['import_run_id' => (int)$run->id, 'entity_type' => 'fabric'])
            ->andWhere(['not in', 'status', [CatalogImportMediaQueue::STATUS_FOLDER]])
            ->count();

        $done = (int)CatalogImportMediaQueue::find()
            ->where(['import_run_id' => (int)$run->id, 'entity_type' => 'fabric'])
            ->andWhere(['status' => [CatalogImportMediaQueue::STATUS_DONE, CatalogImportMediaQueue::STATUS_FAILED]])
            ->count();

        if ($run->hasAttribute('media_total')) {
            $run->media_total = max($total, (int)$run->media_total);
        }
        if ($run->hasAttribute('media_processed_rows')) {
            $run->media_processed_rows = $done;
        }

        $run->phase = CatalogImportRun::PHASE_MEDIA;
        $run->phase_message = sprintf(
            'Загрузка фото: %d из %d (ошибок в партии: %d)',
            $done,
            max($total, 1),
            $batchFailed
        );
        $run->save(false);

        $payload = $run->getStats();
        if (!isset($payload['stats']) || !is_array($payload['stats'])) {
            $payload['stats'] = [];
        }
        $payload['stats']['media_queue_failed'] = (int)($payload['stats']['media_queue_failed'] ?? 0) + $batchFailed;
        $payload['stats']['photo_imported'] = (int)CatalogImportMediaQueue::find()
            ->where([
                'import_run_id' => (int)$run->id,
                'status' => CatalogImportMediaQueue::STATUS_DONE,
            ])
            ->count();
        $run->setStats($payload);
        $run->save(false, ['stats_json']);

        Yii::info(
            sprintf('[MEDIA_QUEUE] run #%d done=%d/%d fail_batch=%d', (int)$run->id, $done, $total, $batchFailed),
            'fabric-import'
        );
    }
}
