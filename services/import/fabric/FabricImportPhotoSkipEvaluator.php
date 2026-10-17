<?php

namespace app\services\import\fabric;

use app\models\CatalogFabricColor;

/**
 * Пропуск загрузки/очереди/webp, если URL фото в реестре не менялся и файл уже привязан.
 */
final class FabricImportPhotoSkipEvaluator
{
    /**
     * @param string|null $previousSourcePhotoUrl URL до перезаписи source_photo_url в этой строке импорта
     */
    public static function skipReason(
        CatalogFabricColor $link,
        FabricRegistryRowDto $row,
        ?string $previousSourcePhotoUrl = null,
    ): ?string {
        if (!self::linkHasStoredPhoto($link)) {
            return null;
        }

        if ((bool)($link->import_media_pending ?? false)) {
            return null;
        }

        $incoming = trim((string)($row->textureUrl ?? ''));
        if ($incoming === '') {
            return null;
        }

        $previous = trim((string)($previousSourcePhotoUrl ?? ''));
        if ($previous === '') {
            $previous = trim((string)($link->source_photo_url ?? ''));
        }

        if ($previous !== '' && FabricRegistryPhotoUrlNormalizer::urlsMatchForImport($previous, $incoming)) {
            return 'Ссылка на фото не изменилась — загрузка и обработка пропущены.';
        }

        $downloadUrl = CloudStorageUrlResolver::resolve($incoming);
        if ($downloadUrl === null) {
            return null;
        }

        $fingerprint = hash('sha256', FabricRegistryPhotoUrlNormalizer::comparisonKey($downloadUrl));
        $stored = trim((string)($link->import_media_fingerprint ?? ''));
        if ($stored !== '' && hash_equals($stored, $fingerprint)) {
            return 'Фото уже импортировано (тот же файл) — загрузка пропущена.';
        }

        return null;
    }

    private static function linkHasStoredPhoto(CatalogFabricColor $link): bool
    {
        return (int)$link->swatch_media_id > 0 || (int)$link->pbr_media_id > 0;
    }
}
