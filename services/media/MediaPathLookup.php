<?php

namespace app\services\media;

use app\models\MediaFile;

class MediaPathLookup
{
    public static function normalizePublicPath(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            $parsed = parse_url($url);
            $url = (string)($parsed['path'] ?? $url);
        }

        return ltrim($url, '/');
    }

    public function findByPublicPath(string $url): ?MediaFile
    {
        $path = self::normalizePublicPath($url);
        if ($path === '') {
            return null;
        }

        return MediaFile::find()
            ->where([
                'or',
                ['path' => $path],
                ['path_large' => $path],
                ['path_medium' => $path],
                ['path_mini' => $path],
                ['path_listing_medium' => $path],
                ['path_listing_mini' => $path],
            ])
            ->one();
    }

    public function findIdByPublicPath(string $url): ?int
    {
        $media = $this->findByPublicPath($url);

        return $media !== null ? (int)$media->id : null;
    }
}
