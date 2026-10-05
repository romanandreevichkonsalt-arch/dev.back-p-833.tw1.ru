<?php

namespace app\services\import\catalog;

use app\services\import\fabric\MediaUrlClassifier;

final class CatalogModelImportPhotoParser
{
    /**
     * @return list<string>
     */
    public static function parseGalleryUrls(string $raw): array
    {
        $urls = [];
        foreach (self::splitUrlList($raw) as $part) {
            if (MediaUrlClassifier::classify($part) === MediaUrlClassifier::TYPE_DIRECT) {
                $urls[] = $part;
            }
        }

        return $urls;
    }

    /**
     * @return array{dimensionPhotoUrls: list<string>, dimensionPhotoFolderUrl: ?string}
     */
    public static function parseDimensionField(string $raw): array
    {
        $dimensionImages = [];
        $folderUrl = null;

        foreach (self::splitUrlList($raw) as $part) {
            $type = MediaUrlClassifier::classify($part);
            if ($type === MediaUrlClassifier::TYPE_FOLDER) {
                $folderUrl = $part;
            } elseif ($type === MediaUrlClassifier::TYPE_DIRECT) {
                $dimensionImages[] = $part;
            }
        }

        return [
            'dimensionPhotoUrls' => $dimensionImages,
            'dimensionPhotoFolderUrl' => $folderUrl,
        ];
    }

    /**
     * Колонка «Фото …» + «Тех Фото» в одном заголовке: прямые ссылки — интерьер, папка — тех. фото.
     *
     * @return array{
     *     galleryPhotoUrls: list<string>,
     *     dimensionPhotoUrls: list<string>,
     *     dimensionPhotoFolderUrl: ?string
     * }
     */
    public static function parseCombinedGalleryTechField(string $raw): array
    {
        $gallery = [];
        $folderUrl = null;

        foreach (self::splitUrlList($raw) as $part) {
            $type = MediaUrlClassifier::classify($part);
            if ($type === MediaUrlClassifier::TYPE_FOLDER) {
                $folderUrl = $part;
            } elseif ($type === MediaUrlClassifier::TYPE_DIRECT) {
                $gallery[] = $part;
            }
        }

        return [
            'galleryPhotoUrls' => $gallery,
            'dimensionPhotoUrls' => [],
            'dimensionPhotoFolderUrl' => $folderUrl,
        ];
    }

    /**
     * @return list<string>
     */
    public static function splitUrlList(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }

        $parts = preg_split('/\s*«,\s*»\s*|\s*;\s*|\s*,\s*/u', $raw) ?: [];
        $items = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part !== '') {
                $items[] = $part;
            }
        }

        return $items;
    }

    public static function isCombinedGalleryTechHeader(string $header): bool
    {
        $header = mb_strtolower(str_replace('|', ' ', $header), 'UTF-8');
        $header = preg_replace('/\s+/u', ' ', $header) ?? $header;

        return str_contains($header, 'фото ссылки') && str_contains($header, 'тех фото');
    }
}
