<?php

namespace app\services\media;

use app\models\MediaFile;

class MediaContentValidator
{
    public static function normalizeFilename(string $filename): string
    {
        $filename = trim($filename);
        if ($filename === '') {
            return '';
        }

        $decoded = rawurldecode(str_replace('+', ' ', $filename));

        return $decoded !== '' ? $decoded : $filename;
    }

    public static function pathForVariant(MediaFile $media, string $variant): string
    {
        return match ($variant) {
            'mini' => (string)($media->path_mini ?? $media->path_medium ?? $media->path_large ?? $media->path),
            'medium' => (string)($media->path_medium ?? $media->path_large ?? $media->path),
            'large' => (string)($media->path_large ?? $media->path),
            default => (string)$media->path,
        };
    }

    public static function strictPathForVariant(MediaFile $media, string $variant): ?string
    {
        return match ($variant) {
            'mini' => $media->path_mini,
            'medium' => $media->path_medium,
            'large' => $media->path_large,
            default => $media->path,
        };
    }

    public static function isVariantFileReadable(MediaFile $media, string $variant): bool
    {
        $path = self::strictPathForVariant($media, $variant);
        if ($path === null || $path === '') {
            return false;
        }

        $absolutePath = \Yii::getAlias('@webroot') . '/' . ltrim($path, '/');
        if (!is_file($absolutePath) || filesize($absolutePath) === 0) {
            return false;
        }

        if (!$media->isImage()) {
            return true;
        }

        return self::isImageFile($absolutePath);
    }

    public static function absolutePath(MediaFile $media, string $variant = 'original'): string
    {
        return \Yii::getAlias('@webroot') . '/' . ltrim(self::pathForVariant($media, $variant), '/');
    }

    /**
     * @return list<string>
     */
    public static function variantCascade(string $preferred): array
    {
        return match ($preferred) {
            'mini' => ['mini', 'medium', 'large', 'original'],
            'medium' => ['medium', 'large', 'original', 'mini'],
            'large' => ['large', 'original', 'medium', 'mini'],
            default => ['original', 'large', 'medium', 'mini'],
        };
    }

    public static function resolveReadableVariant(MediaFile $media, string $preferred = 'original'): ?string
    {
        foreach (self::variantCascade($preferred) as $variant) {
            if (self::isReadable($media, $variant)) {
                return $variant;
            }
        }

        return null;
    }

    public static function isReadable(MediaFile $media, string $variant = 'original'): bool
    {
        $absolutePath = self::absolutePath($media, $variant);
        if (!is_file($absolutePath) || filesize($absolutePath) === 0) {
            return false;
        }

        if (!$media->isImage()) {
            return true;
        }

        return self::isImageFile($absolutePath);
    }

    public static function isImageFile(string $absolutePath): bool
    {
        if (!is_file($absolutePath) || filesize($absolutePath) === 0) {
            return false;
        }

        $header = file_get_contents($absolutePath, false, null, 0, 32);
        if ($header === false) {
            return false;
        }

        $trimmed = ltrim($header);
        if ($trimmed === '' || $trimmed[0] === '<') {
            return false;
        }

        return @getimagesize($absolutePath) !== false;
    }

    public static function isDocumentFile(string $absolutePath): bool
    {
        if (!is_file($absolutePath) || filesize($absolutePath) === 0) {
            return false;
        }

        $header = file_get_contents($absolutePath, false, null, 0, 8);
        if ($header === false || $header === '') {
            return false;
        }

        if (str_starts_with($header, '%PDF')) {
            return true;
        }

        if (str_starts_with($header, 'PK')) {
            return true;
        }

        return !str_starts_with(ltrim($header), '<');
    }

    public static function guessDocumentExtension(string $absolutePath): string
    {
        $ext = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
        if ($ext !== '' && !in_array($ext, ['php', 'tmp'], true) && strlen($ext) <= 8) {
            return $ext;
        }

        $mime = mime_content_type($absolutePath) ?: '';

        return match (true) {
            str_contains($mime, 'pdf') => 'pdf',
            str_contains($mime, 'zip') => 'zip',
            str_contains($mime, 'gltf') && str_contains($mime, 'binary') => 'glb',
            str_contains($mime, 'gltf') => 'gltf',
            default => 'bin',
        };
    }

    public static function filenameFromContentDisposition(?string $headerValue): ?string
    {
        $headerValue = trim((string)$headerValue);
        if ($headerValue === '') {
            return null;
        }

        if (preg_match("/filename\\*=UTF-8''([^;\\s]+)/i", $headerValue, $matches) === 1) {
            $filename = rawurldecode($matches[1]);

            return self::normalizeFilename($filename) ?: null;
        }

        if (preg_match('/filename="([^"]+)"/i', $headerValue, $matches) === 1) {
            return self::normalizeFilename($matches[1]) ?: null;
        }

        if (preg_match('/filename=([^;\\s]+)/i', $headerValue, $matches) === 1) {
            return self::normalizeFilename(trim($matches[1], '"')) ?: null;
        }

        return null;
    }

    public static function extensionFromFilename(?string $filename): ?string
    {
        $filename = self::normalizeFilename(trim((string)$filename));
        if ($filename === '') {
            return null;
        }

        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if ($extension === '' || in_array($extension, ['php', 'tmp'], true)) {
            return null;
        }

        if (strlen($extension) > 8 || preg_match('/^[a-z0-9]+$/', $extension) !== 1) {
            return null;
        }

        return $extension;
    }

    public static function guessStorageBasename(
        string $absolutePath,
        string $preferredBasename,
        ?string $downloadedFilename = null
    ): string {
        $preferredBasename = self::normalizeFilename(trim($preferredBasename));
        if ($preferredBasename === '') {
            $preferredBasename = 'file';
        }

        $ext = strtolower(pathinfo($preferredBasename, PATHINFO_EXTENSION));
        if ($ext !== '' && !in_array($ext, ['php', 'tmp'], true)) {
            return $preferredBasename;
        }

        $downloadedExtension = self::extensionFromFilename($downloadedFilename);
        if ($downloadedExtension !== null) {
            return $preferredBasename . '.' . $downloadedExtension;
        }

        return $preferredBasename . '.' . self::guessDocumentExtension($absolutePath);
    }
}
