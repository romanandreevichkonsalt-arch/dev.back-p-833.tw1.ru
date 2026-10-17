<?php

namespace app\services\import\catalog;

use app\models\CatalogImportMediaQueue;
use app\models\CatalogImportRun;
use app\models\CatalogModel;
use app\models\CatalogModelDimensionImage;
use app\models\CatalogModelImage;
use app\models\MediaFile;
use app\models\MediaFolder;
use app\services\import\fabric\FabricImportMediaDedup;
use app\services\import\fabric\FabricMediaBatchDownloader;
use app\services\import\fabric\FabricMediaDownloadResult;
use app\services\import\fabric\MediaImportService;
use Yii;

final class CatalogModelImportMediaQueueProcessor
{
    private const DEFAULT_BATCH = 80;
    private const DEFAULT_CONCURRENCY = 8;

    /** @var list<string> */
    private const ENTITY_TYPES = ['model_interior', 'model_dimension'];

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

        $run = CatalogImportRun::findOne(['id' => $runId, 'type' => CatalogImportRun::TYPE_MODEL]);
        if ($run === null) {
            return ['processed' => 0, 'failed' => 0, 'pending' => 0, 'continue' => false];
        }

        $items = CatalogImportMediaQueue::find()
            ->where([
                'import_run_id' => $runId,
                'status' => CatalogImportMediaQueue::STATUS_PENDING,
            ])
            ->andWhere(['entity_type' => self::ENTITY_TYPES])
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
                'kind' => MediaFile::KIND_IMAGE,
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

                if ($this->processDownloadResult($item, $download)) {
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
    ): bool {
        if (!$download->success || $download->tempPath === null) {
            return $this->fallbackSingleDownload($item, $download->errorMessage ?? 'Ошибка загрузки');
        }

        $model = CatalogModel::findOne((int)$item->entity_id);
        if ($model === null) {
            @unlink($download->tempPath);
            $this->markFailed($item, 'Модель не найдена.');

            return false;
        }

        $url = trim((string)$item->source_url);
        $entityType = (string)$item->entity_type;
        $slot = (int)($item->slot_index ?? 0);
        $folderSlug = $entityType === 'model_dimension' ? MediaFolder::SLUG_TECH : MediaFolder::SLUG_INTERIOR;
        $basenamePrefix = $entityType === 'model_dimension' ? 'tech' : 'interior';

        try {
            $media = $this->mediaImportService->importCatalogMediaFromDownloadedFile(
                $download->tempPath,
                $url,
                'model-' . $model->id . '-' . $basenamePrefix . '-' . ($slot + 1),
                MediaFile::KIND_IMAGE,
                $folderSlug,
                $download->downloadedFilename,
            );
        } catch (\Throwable $exception) {
            @unlink($download->tempPath);

            return $this->fallbackSingleDownload($item, $exception->getMessage());
        } finally {
            if (is_file($download->tempPath)) {
                @unlink($download->tempPath);
            }
        }

        return $this->finalizeItemWithMedia($item, $media, (int)$item->import_run_id);
    }

    private function fallbackSingleDownload(CatalogImportMediaQueue $item, string $reason): bool
    {
        $model = CatalogModel::findOne((int)$item->entity_id);
        if ($model === null) {
            $this->markFailed($item, $reason);

            return false;
        }

        $url = trim((string)$item->source_url);
        if ($url === '') {
            $this->markFailed($item, $reason);

            return false;
        }

        $entityType = (string)$item->entity_type;
        $slot = (int)($item->slot_index ?? 0);
        $folderSlug = $entityType === 'model_dimension' ? MediaFolder::SLUG_TECH : MediaFolder::SLUG_INTERIOR;
        $basenamePrefix = $entityType === 'model_dimension' ? 'tech' : 'interior';

        try {
            $media = $this->mediaImportService->importCatalogMedia($url, 'model-' . $model->id . '-' . $basenamePrefix . '-' . ($slot + 1), MediaFile::KIND_IMAGE, $folderSlug);
            if ($media === null) {
                $this->markFailed($item, $reason);

                return false;
            }

            return $this->finalizeItemWithMedia($item, $media, (int)$item->import_run_id);
        } catch (\Throwable $exception) {
            $this->markFailed($item, $reason . ' | ' . $exception->getMessage());

            return false;
        }
    }

    private function finalizeItemWithMedia(
        CatalogImportMediaQueue $item,
        MediaFile $media,
        int $runId,
    ): bool {
        if (!$this->attachMediaToItem($item, (int)$media->id)) {
            return false;
        }

        $fingerprint = trim((string)($item->url_fingerprint ?? ''));
        if ($fingerprint !== '') {
            $this->propagateFingerprintSuccess($runId, $fingerprint, (int)$media->id);
        }

        return true;
    }

    private function attachMediaToItem(CatalogImportMediaQueue $item, int $mediaId): bool
    {
        $model = CatalogModel::findOne((int)$item->entity_id);
        if ($model === null) {
            $this->markFailed($item, 'Модель не найдена.');

            return false;
        }

        $slot = (int)($item->slot_index ?? 0);
        $excelSource = trim((string)($item->resolved_filename ?? ''));
        if ($excelSource === '') {
            $excelSource = trim((string)$item->source_url);
        }

        $entityType = (string)$item->entity_type;
        if ($entityType === 'model_dimension') {
            $link = new CatalogModelDimensionImage([
                'model_id' => (int)$model->id,
                'media_file_id' => $mediaId,
                'sort_order' => $slot,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            if ($link->hasAttribute('source_url')) {
                $link->source_url = mb_substr($excelSource, 0, 512);
            }
        } else {
            $link = new CatalogModelImage([
                'model_id' => (int)$model->id,
                'media_file_id' => $mediaId,
                'purpose' => CatalogModelImage::PURPOSE_INTERIOR,
                'sort_order' => $slot,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            if ($link->hasAttribute('source_url')) {
                $link->source_url = mb_substr($excelSource, 0, 512);
            }
        }

        if (!$link->save(false)) {
            $this->markFailed($item, 'Не удалось привязать фото к модели.');

            return false;
        }

        $item->status = CatalogImportMediaQueue::STATUS_DONE;
        $item->media_file_id = $mediaId;
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
            ->andWhere(['entity_type' => self::ENTITY_TYPES])
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
    }

    private function countPending(int $runId): int
    {
        return (int)CatalogImportMediaQueue::find()
            ->where([
                'import_run_id' => $runId,
                'status' => CatalogImportMediaQueue::STATUS_PENDING,
            ])
            ->andWhere(['entity_type' => self::ENTITY_TYPES])
            ->count();
    }

    private function updateRunMediaProgress(CatalogImportRun $run, int $batchFailed): void
    {
        $runId = (int)$run->id;
        $total = (int)CatalogImportMediaQueue::find()
            ->where(['import_run_id' => $runId])
            ->andWhere(['entity_type' => self::ENTITY_TYPES])
            ->andWhere(['not in', 'status', [CatalogImportMediaQueue::STATUS_FOLDER]])
            ->count();

        $done = (int)CatalogImportMediaQueue::find()
            ->where(['import_run_id' => $runId])
            ->andWhere(['entity_type' => self::ENTITY_TYPES])
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
                'import_run_id' => $runId,
                'status' => CatalogImportMediaQueue::STATUS_DONE,
            ])
            ->andWhere(['entity_type' => self::ENTITY_TYPES])
            ->count();
        $run->setStats($payload);
        $run->save(false, ['stats_json']);

        Yii::info(
            sprintf('[MODEL_MEDIA_QUEUE] run #%d done=%d/%d fail_batch=%d', $runId, $done, $total, $batchFailed),
            'catalog-model-import'
        );
    }
}
