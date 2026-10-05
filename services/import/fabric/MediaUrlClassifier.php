<?php

namespace app\services\import\fabric;

class MediaUrlClassifier
{
    public const TYPE_EMPTY = 'empty';
    public const TYPE_FOLDER = 'folder';
    public const TYPE_NON_DIRECT = 'non_direct';
    public const TYPE_DIRECT = 'direct';

    private const DIRECT_EXTENSIONS = 'jpe?g|png|gif|webp|bmp|svg|pdf|zip|glb|gltf|fbx|obj|3ds|dae|blend';

    public static function classify(?string $url): string
    {
        $url = trim((string)$url);
        if ($url === '') {
            return self::TYPE_EMPTY;
        }

        $lower = mb_strtolower($url);

        if (preg_match('~^https?://~i', $url) !== 1 && preg_match('~^//~', $url) !== 1) {
            return self::TYPE_NON_DIRECT;
        }

        if (str_ends_with(rtrim($url, ' '), '/')) {
            return self::TYPE_FOLDER;
        }

        if (preg_match('~disk\.yandex\.(ru|com)/d/~i', $url) === 1) {
            return self::hasDirectExtension($url) ? self::TYPE_DIRECT : self::TYPE_FOLDER;
        }

        if (preg_match('~cloud\.mail\.ru/public/.+/~i', $url) === 1 && !self::hasDirectExtension($url)) {
            return self::TYPE_FOLDER;
        }

        if (preg_match('~drive\.google\.com/(?:drive/)?folders/~i', $url) === 1) {
            return self::TYPE_FOLDER;
        }

        if (preg_match('~^https?://(?:www\.)?souz-m\.ru/products/~i', $url) === 1) {
            return self::TYPE_NON_DIRECT;
        }

        if (preg_match('~drive\.google\.com/file/d/~i', $url) === 1) {
            return self::TYPE_NON_DIRECT;
        }

        if (preg_match('~/folder~i', $url) === 1) {
            return self::TYPE_FOLDER;
        }

        if (self::hasDirectExtension($url)) {
            return self::TYPE_DIRECT;
        }

        if (preg_match('~docs\.google\.com/(spreadsheets|document|presentation)~i', $url) === 1) {
            return self::TYPE_NON_DIRECT;
        }

        return self::TYPE_NON_DIRECT;
    }

    public static function isDirectFileUrl(?string $url): bool
    {
        return self::classify($url) === self::TYPE_DIRECT;
    }

    public static function shouldSkipPhoto(?string $url): bool
    {
        return self::classify($url) !== self::TYPE_DIRECT;
    }

    private static function hasDirectExtension(string $url): bool
    {
        $path = parse_url($url, PHP_URL_PATH) ?? $url;

        return preg_match('~\.(?:' . self::DIRECT_EXTENSIONS . ')$~i', $path) === 1;
    }
}
