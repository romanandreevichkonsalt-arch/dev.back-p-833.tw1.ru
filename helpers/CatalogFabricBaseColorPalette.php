<?php

namespace app\helpers;

final class CatalogFabricBaseColorPalette
{
    /**
     * @return list<array{label:string,slug?:string,hex:string}>
     */
    public static function entries(): array
    {
        return [
            ['label' => 'Белый', 'slug' => 'belyy', 'hex' => '#FFFFFF'],
            ['label' => 'Кремовый', 'hex' => '#F5F0E6'],
            ['label' => 'Бежевый', 'slug' => 'bezhevyy', 'hex' => '#D4C4A8'],
            ['label' => 'Песочный', 'hex' => '#D9C4A0'],
            ['label' => 'Капучино', 'hex' => '#C4A484'],

            ['label' => 'Серый', 'slug' => 'seryy', 'hex' => '#9E9E9E'],
            ['label' => 'Чёрный', 'hex' => '#1F1F1F'],

            ['label' => 'Коричневый', 'slug' => 'korichnevyy', 'hex' => '#7A4E2D'],
            ['label' => 'Терракота', 'slug' => 'terrakota', 'hex' => '#C86B4A'],
            ['label' => 'Оранжевый', 'hex' => '#E07A2D'],

            ['label' => 'Жёлтый', 'hex' => '#E6C84A'],
            ['label' => 'Желтый', 'slug' => 'zheltyy', 'hex' => '#E6C84A'],
            ['label' => 'Горчичный', 'slug' => 'gorchichnyy', 'hex' => '#C9A227'],

            ['label' => 'Красный', 'slug' => 'krasnyy', 'hex' => '#C62828'],
            ['label' => 'Бордовый', 'hex' => '#7B1E3A'],
            ['label' => 'Розовый', 'hex' => '#E8A0A8'],

            ['label' => 'Синий', 'slug' => 'siniy', 'hex' => '#2F5F9E'],
            ['label' => 'Голубой', 'hex' => '#7EB6D8'],

            ['label' => 'Зелёный', 'hex' => '#4F8A4A'],
            ['label' => 'Зеленый', 'slug' => 'zelenyy', 'hex' => '#4F8A4A'],
            ['label' => 'Оливковый', 'slug' => 'olivkovyy', 'hex' => '#6B6F3A'],
            ['label' => 'Мятный', 'hex' => '#9FD4C4'],

            ['label' => 'Фиолетовый', 'hex' => '#6A4C93'],
            ['label' => 'Сиреневый', 'hex' => '#B39DDB'],
        ];
    }

    public static function hexForLabel(string $label): ?string
    {
        $normalized = mb_strtolower(trim($label), 'UTF-8');
        foreach (self::entries() as $entry) {
            if (mb_strtolower($entry['label'], 'UTF-8') === $normalized) {
                return $entry['hex'];
            }
        }

        return null;
    }

    public static function hexForSlug(string $slug): ?string
    {
        $slug = trim($slug);
        if ($slug === '') {
            return null;
        }

        foreach (self::entries() as $entry) {
            $entrySlug = $entry['slug'] ?? SlugHelper::slugify($entry['label']);
            if ($entrySlug === $slug) {
                return $entry['hex'];
            }
        }

        return null;
    }

    public static function resolveHex(?string $label, ?string $slug = null): ?string
    {
        if ($label !== null && trim($label) !== '') {
            $hex = self::hexForLabel($label);
            if ($hex !== null) {
                return $hex;
            }
        }

        if ($slug !== null && trim($slug) !== '') {
            return self::hexForSlug($slug);
        }

        return null;
    }
}
