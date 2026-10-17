<?php

namespace app\services\import\catalog;

use app\models\CatalogImportMediaQueue;
use app\models\CatalogModel;
use app\models\CatalogModelDimensionImage;
use app\models\CatalogModelImage;
use app\services\import\fabric\CloudStorageUrlResolver;
use app\services\import\fabric\FabricImportMediaDedup;
use app\services\import\fabric\MediaUrlClassifier;

/**
 * Очередь загрузки галереи и габаритных фото модели (async-импорт).
 */
final class CatalogModelImportMediaEnqueuer
{
    public function __construct(
        private readonly FabricImportMediaDedup $dedup = new FabricImportMediaDedup(),
    ) {
    }

    /**
     * @param list<string> $messages
     */
    public function enqueueGalleryAndDimensions(
        CatalogModel $model,
        CatalogModelImportRowDto $row,
        int $importRunId,
        CatalogModelImportResult $result,
        array &$messages,
    ): void {
        if ($row->galleryPhotoUrls !== []) {
            $this->enqueueUrlList(
                $model,
                $row,
                $importRunId,
                $row->galleryPhotoUrls,
                CatalogModelImage::PURPOSE_INTERIOR,
                'model_interior',
                CatalogModelImage::class,
                'interior',
                $result,
                $messages,
            );
        }

        if ($row->dimensionPhotoUrls !== []) {
            $this->enqueueUrlList(
                $model,
                $row,
                $importRunId,
                $row->dimensionPhotoUrls,
                null,
                'model_dimension',
                CatalogModelDimensionImage::class,
                'tech',
                $result,
                $messages,
            );
        } elseif ($row->dimensionPhotoFolderUrl !== null && $row->dimensionPhotoFolderUrl !== '') {
            CatalogModelDimensionImage::deleteAll(['model_id' => $model->id]);
        }
    }

    /**
     * @param list<string> $urls
     * @param class-string<CatalogModelImage|CatalogModelDimensionImage> $linkClass
     * @param list<string> $messages
     */
    private function enqueueUrlList(
        CatalogModel $model,
        CatalogModelImportRowDto $row,
        int $importRunId,
        array $urls,
        ?string $purpose,
        string $entityType,
        string $linkClass,
        string $basenamePrefix,
        CatalogModelImportResult $result,
        array &$messages,
    ): void {
        /** @var list<CatalogModelImage|CatalogModelDimensionImage> $existing */
        $existing = $this->loadExistingLinks($model, $linkClass, $purpose);

        $urlCount = count($urls);
        for ($i = $urlCount; $i < count($existing); $i++) {
            $extra = $existing[$i];
            $extraSource = trim((string)($extra->source_url ?? ''));
            // Лишние слоты с импортного source_url убираем; ручные доп. фото без source_url не трогаем.
            if ($extraSource !== '') {
                $extra->delete();
            }
        }

        foreach ($urls as $slotIndex => $rawUrl) {
            $incomingUrl = trim($rawUrl);
            if ($incomingUrl === '') {
                continue;
            }

            $existingLink = $existing[$slotIndex] ?? null;
            $storedSource = $existingLink !== null ? (string)($existingLink->source_url ?? '') : '';
            $hasMedia = $existingLink !== null && (int)$existingLink->media_file_id > 0;

            $skipReason = CatalogModelImportPhotoSkipEvaluator::skipReason($storedSource, $incomingUrl, $hasMedia);
            if ($skipReason !== null) {
                if ($storedSource === '' && $incomingUrl !== '' && $existingLink !== null && $existingLink->hasAttribute('source_url')) {
                    $existingLink->source_url = mb_substr($incomingUrl, 0, 512);
                    $existingLink->save(false, ['source_url']);
                }
                $result->stats['photo_skipped'] = (int)($result->stats['photo_skipped'] ?? 0) + 1;
                $messages[] = $skipReason;

                continue;
            }

            if ($existingLink !== null) {
                $existingLink->delete();
            }

            $downloadUrl = CloudStorageUrlResolver::resolve($incomingUrl);
            if ($downloadUrl === null) {
                $result->stats['photo_skipped'] = (int)($result->stats['photo_skipped'] ?? 0) + 1;
                $reason = CloudStorageUrlResolver::unsupportedReason($incomingUrl)
                    ?? 'Ссылка не является прямым файлом изображения.';
                $messages[] = 'Фото не загружено: ' . $reason;

                continue;
            }

            $fingerprint = CatalogModelImportPhotoSkipEvaluator::urlFingerprint($incomingUrl);

            $reusedMediaId = $this->dedup->findMediaIdByFingerprint($fingerprint);
            if ($reusedMediaId !== null && $this->attachExistingLink(
                $model,
                $linkClass,
                $purpose,
                $slotIndex,
                $incomingUrl,
                $reusedMediaId,
            )) {
                $result->stats['photo_imported'] = (int)($result->stats['photo_imported'] ?? 0) + 1;
                $messages[] = 'Фото взято из кеша (уже загружалось ранее).';

                continue;
            }

            if ($this->dedup->hasPendingInRun($importRunId, $fingerprint)) {
                $result->stats['photo_queued'] = (int)($result->stats['photo_queued'] ?? 0) + 1;
                $messages[] = 'Фото уже в очереди загрузки (тот же URL).';

                continue;
            }

            $this->createQueueItem(
                $importRunId,
                $row->rowNumber,
                $downloadUrl,
                $fingerprint,
                $entityType,
                (int)$model->id,
                $slotIndex,
                $incomingUrl,
            );
            $result->stats['photo_queued'] = (int)($result->stats['photo_queued'] ?? 0) + 1;
            $messages[] = 'Фото поставлено в очередь загрузки.';
        }
    }

    /**
     * @param class-string<CatalogModelImage|CatalogModelDimensionImage> $linkClass
     * @return list<CatalogModelImage|CatalogModelDimensionImage>
     */
    private function loadExistingLinks(CatalogModel $model, string $linkClass, ?string $purpose): array
    {
        $query = $linkClass::find()
            ->where(['model_id' => (int)$model->id])
            ->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC]);

        if ($purpose !== null && $linkClass === CatalogModelImage::class) {
            $query->andWhere(['purpose' => $purpose]);
        }

        return $query->all();
    }

    /**
     * @param class-string<CatalogModelImage|CatalogModelDimensionImage> $linkClass
     */
    private function attachExistingLink(
        CatalogModel $model,
        string $linkClass,
        ?string $purpose,
        int $slotIndex,
        string $sourceUrl,
        int $mediaId,
    ): bool {
        $link = new $linkClass([
            'model_id' => (int)$model->id,
            'media_file_id' => $mediaId,
            'sort_order' => $slotIndex,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        if ($link instanceof CatalogModelImage && $purpose !== null) {
            $link->purpose = $purpose;
        }
        if ($link->hasAttribute('source_url')) {
            $link->source_url = mb_substr($sourceUrl, 0, 512);
        }

        return $link->save(false);
    }

    private function createQueueItem(
        int $importRunId,
        int $rowNumber,
        string $downloadUrl,
        string $fingerprint,
        string $entityType,
        int $modelId,
        int $slotIndex,
        string $excelSourceUrl,
    ): void {
        $queue = new CatalogImportMediaQueue([
            'import_run_id' => $importRunId,
            'row_number' => $rowNumber,
            'source_url' => mb_substr($downloadUrl, 0, 512),
            'url_type' => MediaUrlClassifier::classify($downloadUrl),
            'status' => CatalogImportMediaQueue::STATUS_PENDING,
            'entity_type' => $entityType,
            'entity_id' => $modelId,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        if ($queue->hasAttribute('url_fingerprint')) {
            $queue->url_fingerprint = $fingerprint;
        }
        if ($queue->hasAttribute('slot_index')) {
            $queue->slot_index = $slotIndex;
        }
        if ($queue->hasAttribute('resolved_filename')) {
            $queue->resolved_filename = mb_substr($excelSourceUrl, 0, 255);
        }
        $queue->save(false);
    }
}
