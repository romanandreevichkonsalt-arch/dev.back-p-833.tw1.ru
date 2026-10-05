<?php

namespace app\services\import\surface;

use app\services\import\fabric\FabricMediaNaming;

class SurfaceMaterialMediaNaming
{
    public static function photo(string $typeSlug, string $materialSlug, string $extension): string
    {
        return self::build($typeSlug, $materialSlug, 'photo', $extension);
    }

    public static function texture(string $typeSlug, string $materialSlug, string $extension): string
    {
        return self::build($typeSlug, $materialSlug, 'texture', $extension);
    }

    public static function extensionFromUrl(string $url): string
    {
        return FabricMediaNaming::extensionFromUrl($url);
    }

    private static function build(string $typeSlug, string $materialSlug, string $suffix, string $extension): string
    {
        $typeSlug = self::sanitizeSegment($typeSlug);
        $materialSlug = self::sanitizeSegment($materialSlug);
        $extension = ltrim(strtolower($extension), '.');

        return sprintf('%s-%s-%s.%s', $typeSlug, $materialSlug, $suffix, $extension !== '' ? $extension : 'jpg');
    }

    private static function sanitizeSegment(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9._-]+/', '-', $value) ?? $value;
        $value = trim($value, '.-');

        return $value !== '' ? $value : 'item';
    }
}
