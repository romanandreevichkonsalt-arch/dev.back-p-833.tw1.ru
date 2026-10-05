<?php

namespace app\services\import\fabric;

class FabricDesignCodeNormalizer
{
    /**
     * Значение из колонки «Цветодизайн» — сохраняем как название цвета в коллекции.
     */
    public static function fromRegistry(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (preg_match('/^\d+\.0+$/', $value) === 1) {
            return (string)(int)$value;
        }

        return $value;
    }

    /**
     * Slug для имён файлов медиа.
     */
    public static function normalize(string $value): string
    {
        $value = self::fromRegistry($value);
        if ($value === '') {
            return '';
        }

        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $value) ?? '');
        $slug = trim($slug, '-');

        return $slug !== '' ? $slug : strtolower($value);
    }
}
