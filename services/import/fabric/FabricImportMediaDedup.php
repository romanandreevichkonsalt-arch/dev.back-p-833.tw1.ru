<?php

namespace app\services\import\fabric;

use app\models\CatalogImportMediaQueue;
use app\models\MediaFile;

/**
 * Поиск уже импортированного медиа по fingerprint URL (глобально по прошлым прогонам и очереди).
 */
final class FabricImportMediaDedup
{
    public function findMediaIdByFingerprint(string $fingerprint): ?int
    {
        $fingerprint = trim($fingerprint);
        if ($fingerprint === '') {
            return null;
        }

        if (!$this->queueSupportsFingerprint()) {
            return null;
        }

        $queueRow = CatalogImportMediaQueue::find()
            ->where([
                'url_fingerprint' => $fingerprint,
                'status' => CatalogImportMediaQueue::STATUS_DONE,
            ])
            ->andWhere(['not', ['media_file_id' => null]])
            ->orderBy(['id' => SORT_DESC])
            ->one();

        if ($queueRow !== null && (int)$queueRow->media_file_id > 0) {
            $media = MediaFile::findOne((int)$queueRow->media_file_id);

            return $media !== null ? (int)$media->id : null;
        }

        return null;
    }

    public function hasPendingInRun(int $importRunId, string $fingerprint): bool
    {
        if ($fingerprint === '' || !$this->queueSupportsFingerprint()) {
            return false;
        }

        return CatalogImportMediaQueue::find()
            ->where([
                'import_run_id' => $importRunId,
                'url_fingerprint' => $fingerprint,
                'status' => CatalogImportMediaQueue::STATUS_PENDING,
            ])
            ->exists();
    }

    private function queueSupportsFingerprint(): bool
    {
        $schema = CatalogImportMediaQueue::getTableSchema();

        return $schema !== null && $schema->getColumn('url_fingerprint') !== null;
    }
}
