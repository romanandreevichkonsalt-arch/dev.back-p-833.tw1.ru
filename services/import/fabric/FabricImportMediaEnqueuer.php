<?php

namespace app\services\import\fabric;

use app\models\CatalogFabricColor;
use app\models\CatalogImportMediaQueue;

/**
 * Постановка загрузки фото образца в очередь (без синхронного curl в импорте строк).
 */
final class FabricImportMediaEnqueuer
{
    public function __construct(
        private readonly FabricImportMediaDedup $dedup = new FabricImportMediaDedup(),
    ) {
    }

    /**
     * @return bool true если задача поставлена или уже не нужна; false если URL не поддерживается
     */
    public function enqueueForLink(
        FabricRegistryRowDto $row,
        CatalogFabricColor $link,
        ?int $importRunId,
        FabricRegistryImportRowResult $rowResult,
        ?string $previousSourcePhotoUrl = null,
    ): bool {
        $textureUrl = $row->textureUrl;
        if ($textureUrl === null || trim($textureUrl) === '') {
            return false;
        }

        $skipReason = FabricImportPhotoSkipEvaluator::skipReason($link, $row, $previousSourcePhotoUrl);
        if ($skipReason !== null) {
            $rowResult->photoSkipped = true;
            $rowResult->addMessage($skipReason);

            return true;
        }

        $downloadUrl = CloudStorageUrlResolver::resolve($textureUrl);
        if ($downloadUrl === null) {
            $rowResult->photoSkipped = true;
            $reason = CloudStorageUrlResolver::unsupportedReason($textureUrl)
                ?? 'Ссылка не является прямым файлом изображения.';
            $rowResult->addMessage(
                'Фото не загружено: ' . $reason . ' Сохранена исходная ссылка для ручной загрузки.'
            );
            $this->enqueueUnsupported($importRunId, $row, $textureUrl, $link);

            return false;
        }

        $fingerprint = $this->urlFingerprint($downloadUrl);

        $reusedMediaId = $this->dedup->findMediaIdByFingerprint($fingerprint);
        if ($reusedMediaId !== null && $this->attachExistingMedia($link, $reusedMediaId, $fingerprint, $rowResult)) {
            return true;
        }

        if ($importRunId !== null && $this->dedup->hasPendingInRun($importRunId, $fingerprint)) {
            $rowResult->photoQueued = true;
            $rowResult->addMessage('Фото уже в очереди загрузки (тот же URL).');
            $this->markLinkAwaitingMedia($link, $fingerprint, $row);

            return true;
        }

        if ($this->queueHasPendingOrDone($fingerprint, (int)$link->id)) {
            $rowResult->photoQueued = true;
            $rowResult->addMessage('Фото уже в очереди загрузки.');
            $this->markLinkAwaitingMedia($link, $fingerprint, $row);

            return true;
        }

        if ($importRunId === null || $importRunId <= 0) {
            $rowResult->photoSkipped = true;
            $rowResult->addMessage('Очередь медиа доступна только для async-импорта с import_run_id.');

            return false;
        }

        $urlType = MediaUrlClassifier::classify($downloadUrl);
        $queue = new CatalogImportMediaQueue([
            'import_run_id' => $importRunId,
            'row_number' => $row->rowNumber,
            'source_url' => mb_substr($downloadUrl, 0, 512),
            'url_type' => $urlType,
            'status' => CatalogImportMediaQueue::STATUS_PENDING,
            'entity_type' => 'fabric',
            'entity_id' => (int)$link->id,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        if ($queue->hasAttribute('url_fingerprint')) {
            $queue->url_fingerprint = $fingerprint;
        }
        $queue->save(false);

        $this->markLinkAwaitingMedia($link, $fingerprint, $row);

        $rowResult->photoQueued = true;
        $rowResult->addMessage('Фото поставлено в очередь загрузки.');

        return true;
    }

    private function attachExistingMedia(
        CatalogFabricColor $link,
        int $mediaId,
        string $fingerprint,
        FabricRegistryImportRowResult $rowResult,
    ): bool {
        $media = \app\models\MediaFile::findOne($mediaId);
        if ($media === null) {
            return false;
        }

        if ($media->kind === \app\models\MediaFile::KIND_IMAGE) {
            $link->swatch_media_id = $mediaId;
        } else {
            $link->pbr_media_id = $mediaId;
        }
        if ($link->hasAttribute('import_media_pending')) {
            $link->import_media_pending = false;
        }
        if ($link->hasAttribute('import_media_fingerprint')) {
            $link->import_media_fingerprint = $fingerprint;
        }

        $rowResult->photoImported = true;
        $rowResult->addMessage('Фото взято из кеша (уже загружалось ранее).');

        return true;
    }

    private function markLinkAwaitingMedia(
        CatalogFabricColor $link,
        string $fingerprint,
        FabricRegistryRowDto $row,
    ): void {
        if ($link->hasAttribute('import_media_pending')) {
            $link->import_media_pending = true;
        }
        if ($link->hasAttribute('import_media_fingerprint')) {
            $link->import_media_fingerprint = $fingerprint;
        }
        if ($link->hasAttribute('import_row_hash')) {
            $link->import_row_hash = $row->rowHash();
        }
    }

    public function urlFingerprint(string $downloadUrl): string
    {
        return hash('sha256', FabricRegistryPhotoUrlNormalizer::comparisonKey($downloadUrl));
    }

    private function queueHasPendingOrDone(string $fingerprint, int $entityId): bool
    {
        if ($fingerprint === '') {
            return false;
        }

        $schema = CatalogImportMediaQueue::getTableSchema();
        if ($schema === null || $schema->getColumn('url_fingerprint') === null) {
            return false;
        }

        return CatalogImportMediaQueue::find()
            ->where([
                'url_fingerprint' => $fingerprint,
                'entity_id' => $entityId,
                'entity_type' => 'fabric',
            ])
            ->andWhere(['status' => [
                CatalogImportMediaQueue::STATUS_PENDING,
                CatalogImportMediaQueue::STATUS_DONE,
            ]])
            ->exists();
    }

    private function enqueueUnsupported(
        ?int $importRunId,
        FabricRegistryRowDto $row,
        string $url,
        CatalogFabricColor $link,
    ): void {
        if ($importRunId === null || $importRunId <= 0) {
            return;
        }

        $classification = MediaUrlClassifier::classify($url);
        $queue = new CatalogImportMediaQueue([
            'import_run_id' => $importRunId,
            'row_number' => $row->rowNumber,
            'source_url' => mb_substr($url, 0, 512),
            'url_type' => $classification,
            'status' => $classification === MediaUrlClassifier::TYPE_FOLDER
                ? CatalogImportMediaQueue::STATUS_FOLDER
                : CatalogImportMediaQueue::STATUS_PENDING,
            'entity_type' => 'fabric',
            'entity_id' => (int)$link->id,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $queue->save(false);
    }
}
