<?php

namespace app\services\import\fabric;

/**
 * Ключ сравнения URL фото из реестра (исходная строка в Excel не меняется).
 */
final class FabricRegistryPhotoUrlNormalizer
{
    public static function comparisonKey(?string $url): string
    {
        $url = trim((string)$url);
        if ($url === '') {
            return '';
        }

        $parts = parse_url($url);
        if (!is_array($parts)) {
            return $url;
        }

        $scheme = isset($parts['scheme']) ? strtolower((string)$parts['scheme']) . '://' : '';
        $host = isset($parts['host']) ? strtolower((string)$parts['host']) : '';
        $port = isset($parts['port']) ? ':' . (int)$parts['port'] : '';
        $path = (string)($parts['path'] ?? '');
        $query = self::normalizedQueryString($parts['query'] ?? null);

        return $scheme . $host . $port . $path . ($query !== '' ? '?' . $query : '');
    }

    public static function urlsMatchForImport(?string $storedUrl, ?string $incomingUrl): bool
    {
        $stored = trim((string)$storedUrl);
        $incoming = trim((string)$incomingUrl);
        if ($stored === '' || $incoming === '') {
            return false;
        }

        if ($stored === $incoming) {
            return true;
        }

        return self::comparisonKey($stored) === self::comparisonKey($incoming);
    }

    private static function normalizedQueryString(mixed $query): string
    {
        if (!is_string($query) || $query === '') {
            return '';
        }

        parse_str($query, $params);
        if (!is_array($params) || $params === []) {
            return '';
        }

        unset($params['mt']);

        ksort($params);

        return http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }
}
