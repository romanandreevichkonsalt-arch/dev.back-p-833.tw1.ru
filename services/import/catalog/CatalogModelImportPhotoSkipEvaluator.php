<?php

namespace app\services\import\catalog;

use app\services\import\fabric\CloudStorageUrlResolver;
use app\services\import\fabric\FabricRegistryPhotoUrlNormalizer;

/**
 * Пропуск загрузки фото модели (галерея / габариты), если URL в прайсе не менялся.
 */
final class CatalogModelImportPhotoSkipEvaluator
{
    public static function skipReason(?string $storedSourceUrl, string $incomingUrl, bool $hasMediaFile): ?string
    {
        if (!$hasMediaFile) {
            return null;
        }

        $stored = trim((string)$storedSourceUrl);

        // Ручные правки / старый каталог без source_url — импорт не перекачивает и не снимает привязку.
        if ($stored === '') {
            return 'Фото уже в каталоге (нет сохранённой ссылки источника) — загрузка не выполнялась.';
        }

        $incoming = trim($incomingUrl);
        if ($incoming === '') {
            return null;
        }

        if (FabricRegistryPhotoUrlNormalizer::urlsMatchForImport($stored, $incoming)) {
            return 'Ссылка на фото не изменилась — загрузка пропущена.';
        }

        return null;
    }

    public static function urlFingerprint(string $url): string
    {
        $downloadUrl = CloudStorageUrlResolver::resolve($url) ?? $url;

        return hash('sha256', FabricRegistryPhotoUrlNormalizer::comparisonKey($downloadUrl));
    }
}
