<?php

namespace app\services\import\fabric;

class CloudStorageUrlResolver
{
    public static function resolve(?string $url): ?string
    {
        $url = trim((string)$url);
        if ($url === '') {
            return null;
        }

        if (self::isMailRuPublicUrl($url)) {
            return self::resolveMailRuPublic($url);
        }

        if (self::isYandexPublicUrl($url)) {
            return self::resolveYandexPublic($url);
        }

        if (self::isGoogleDriveUrl($url)) {
            return self::resolveGoogleDrive($url);
        }

        if (self::isSouzMProductUrl($url)) {
            return self::resolveSouzMProduct($url);
        }

        return MediaUrlClassifier::isDirectFileUrl($url) ? $url : null;
    }

    public static function unsupportedReason(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        if (preg_match('~drive\.google\.com/(?:drive/)?folders/~i', $url) === 1) {
            return 'Ссылка на папку Google Drive — укажите прямую ссылку на файл изображения.';
        }

        if (self::isYandexPublicUrl($url)) {
            $parsed = self::parseYandexPublicUrl($url);
            if ($parsed !== null && $parsed['path'] === null) {
                return 'Ссылка на папку Яндекс.Диска — укажите прямую ссылку на файл изображения.';
            }
        }

        if (self::isSouzMProductUrl($url)) {
            return 'Не удалось получить фото со страницы каталога souz-m.ru.';
        }

        if (self::isGoogleDriveUrl($url)) {
            return 'Ссылка Google Drive не распознана — используйте ссылку вида drive.google.com/file/d/…';
        }

        if (self::isMailRuPublicUrl($url) || self::isYandexPublicUrl($url)) {
            return 'Не удалось получить прямую ссылку на файл в облаке.';
        }

        return 'Ссылка не является прямым файлом изображения.';
    }

    private static function isMailRuPublicUrl(string $url): bool
    {
        return preg_match('~^https?://cloud\.mail\.ru/public/~i', $url) === 1;
    }

    private static function isYandexPublicUrl(string $url): bool
    {
        return preg_match('~^https?://(?:disk\.yandex\.(?:ru|com)|yadi\.sk)/~i', $url) === 1;
    }

    private static function isGoogleDriveUrl(string $url): bool
    {
        return preg_match('~^https?://drive\.google\.com/~i', $url) === 1;
    }

    private static function isSouzMProductUrl(string $url): bool
    {
        return preg_match('~^https?://(?:www\.)?souz-m\.ru/products/~i', $url) === 1;
    }

    private static function resolveMailRuPublic(string $url): ?string
    {
        $weblink = self::extractMailRuWeblink($url);
        if ($weblink === '') {
            return null;
        }

        $dispatcher = self::fetchJson(
            'https://cloud.mail.ru/api/v2/dispatcher?weblink=' . rawurlencode($weblink)
        );
        if ($dispatcher === null) {
            return null;
        }

        $base = $dispatcher['body']['weblink_view'][0]['url'] ?? null;
        if (!is_string($base) || $base === '') {
            return null;
        }

        return rtrim($base, '/') . '/' . rawurlencode($weblink);
    }

    private static function resolveYandexPublic(string $url): ?string
    {
        $parsed = self::parseYandexPublicUrl($url);
        if ($parsed === null || $parsed['path'] === null) {
            return null;
        }

        $apiUrl = 'https://cloud-api.yandex.net/v1/disk/public/resources/download?public_key='
            . rawurlencode($parsed['public_key'])
            . '&path=' . rawurlencode($parsed['path']);

        $payload = self::fetchJson($apiUrl);
        $href = $payload['href'] ?? null;

        return is_string($href) && $href !== '' ? $href : null;
    }

    /**
     * @return array{public_key:string,path:?string}|null
     */
    private static function parseYandexPublicUrl(string $url): ?array
    {
        if (preg_match(
            '~^https?://(?:disk\.yandex\.(?:ru|com)|yadi\.sk)/d/([^/?#]+)(?:/(.*))?$~iu',
            $url,
            $matches
        ) !== 1) {
            return null;
        }

        $shareKey = $matches[1];
        $rest = isset($matches[2]) ? rawurldecode($matches[2]) : '';
        $rest = trim($rest, '/');
        $publicKey = 'https://disk.yandex.ru/d/' . $shareKey;

        if ($rest === '' || !self::looksLikeFilePath($rest)) {
            return [
                'public_key' => $publicKey,
                'path' => null,
            ];
        }

        return [
            'public_key' => $publicKey,
            'path' => '/' . $rest,
        ];
    }

    private static function resolveGoogleDrive(string $url): ?string
    {
        if (preg_match('~drive\.google\.com/(?:drive/)?folders/~i', $url) === 1) {
            return null;
        }

        if (preg_match('~drive\.google\.com/file/d/([^/?#]+)~i', $url, $matches) === 1) {
            return 'https://drive.google.com/uc?export=download&id=' . $matches[1];
        }

        if (preg_match('~[?&]id=([^&]+)~i', $url, $matches) === 1) {
            return 'https://drive.google.com/uc?export=download&id=' . $matches[1];
        }

        return null;
    }

    private static function resolveSouzMProduct(string $url): ?string
    {
        $html = self::fetchText($url);
        if ($html === null) {
            return null;
        }

        if (preg_match(
            '~<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']~i',
            $html,
            $matches
        ) === 1) {
            return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5);
        }

        if (preg_match(
            '~<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:image["\']~i',
            $html,
            $matches
        ) === 1) {
            return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5);
        }

        return null;
    }

    private static function extractMailRuWeblink(string $url): string
    {
        if (preg_match('~cloud\.mail\.ru/public/(.+?)(?:\?.*)?$~iu', $url, $matches) !== 1) {
            return '';
        }

        $segments = explode('/', $matches[1]);

        return implode('/', array_map('rawurldecode', $segments));
    }

    private static function looksLikeFilePath(string $path): bool
    {
        $filename = basename($path);

        return preg_match(
            '~\.(?:jpe?g|png|gif|webp|bmp|svg|zip|pdf|max|fbx|obj|glb|gltf|blend|3ds|dae)$~i',
            $filename
        ) === 1;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function fetchJson(string $url): ?array
    {
        $response = self::fetchText($url);
        if ($response === null) {
            return null;
        }

        $decoded = json_decode($response, true);

        return is_array($decoded) ? $decoded : null;
    }

    private static function fetchText(string $url): ?string
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return null;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; FabricRegistryImporter/1.0)',
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode >= 400 || $response === '') {
            return null;
        }

        return $response;
    }
}
