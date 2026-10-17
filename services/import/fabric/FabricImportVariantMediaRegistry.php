<?php

namespace app\services\import\fabric;

use app\models\CatalogImportRun;
use app\models\MediaFile;

/**
 * Список MediaFile, для которых webp-варианты нужно собрать после async-импорта.
 */
final class FabricImportVariantMediaRegistry
{
    public static function registerForRun(int $importRunId, MediaFile $media): void
    {
        if ($importRunId <= 0 || !$media->isImage()) {
            return;
        }

        $run = CatalogImportRun::findOne(['id' => $importRunId, 'type' => CatalogImportRun::TYPE_FABRIC]);
        if ($run === null) {
            return;
        }

        $payload = $run->getStats();
        if (!isset($payload['variant_media_ids']) || !is_array($payload['variant_media_ids'])) {
            $payload['variant_media_ids'] = [];
        }

        $mediaId = (int)$media->id;
        if ($mediaId <= 0) {
            return;
        }

        $payload['variant_media_ids'][(string)$mediaId] = $mediaId;
        $run->setStats($payload);
        $run->save(false, ['stats_json']);
    }
}
