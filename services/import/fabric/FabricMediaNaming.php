<?php

namespace app\services\import\fabric;

class FabricMediaNaming
{
    public static function swatch(string $collectionSlug, string $designCode, string $extension): string
    {
        return self::build($collectionSlug, $designCode, 'swatch', $extension);
    }

    public static function pbr(string $collectionSlug, string $designCode, string $extension = 'zip'): string
    {
        return self::build($collectionSlug, $designCode, 'pbr', $extension);
    }

    public static function extensionFromUrl(string $url): string
    {
        return self::optionalExtensionFromUrl($url) ?? 'jpg';
    }

    public static function optionalExtensionFromUrl(string $url): ?string
    {
        $candidates = [];
        $path = parse_url($url, PHP_URL_PATH);
        if (is_string($path) && $path !== '') {
            $candidates[] = $path;
        }

        $query = parse_url($url, PHP_URL_QUERY);
        if (is_string($query) && $query !== '') {
            parse_str($query, $params);
            if (isset($params['path']) && is_string($params['path']) && $params['path'] !== '') {
                $candidates[] = $params['path'];
            }
        }

        foreach ($candidates as $candidate) {
            $extension = strtolower(pathinfo($candidate, PATHINFO_EXTENSION));
            if ($extension !== '' && preg_match('/^[a-z0-9]{1,8}$/', $extension) === 1) {
                return $extension;
            }
        }

        return null;
    }

    private static function build(string $collectionSlug, string $designCode, string $suffix, string $extension): string
    {
        $collectionSlug = self::sanitizeSegment($collectionSlug);
        $designCode = self::sanitizeSegment($designCode);
        $extension = ltrim(strtolower($extension), '.');

        return sprintf('%s-%s-%s.%s', $collectionSlug, $designCode, $suffix, $extension !== '' ? $extension : 'jpg');
    }

    private static function sanitizeSegment(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9._-]+/', '-', $value) ?? $value;
        $value = trim($value, '.-');

        return $value !== '' ? $value : 'item';
    }
}
