<?php

namespace app\helpers;

class SlugHelper
{
    private const TRANSLIT_MAP = [
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e',
        'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm',
        'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u',
        'ф' => 'f', 'х' => 'h', 'ц' => 'ts', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch', 'ъ' => '',
        'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
    ];

    public static function fromString(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $chars = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $result = '';

        foreach ($chars as $char) {
            if (isset(self::TRANSLIT_MAP[$char])) {
                $result .= self::TRANSLIT_MAP[$char];
                continue;
            }

            if (preg_match('/[a-z0-9]/', $char)) {
                $result .= $char;
                continue;
            }

            if ($char === ' ' || $char === '_' || $char === '-') {
                $result .= '-';
            }
        }

        $slug = preg_replace('/-+/', '-', $result) ?? '';
        $slug = trim((string) $slug, '-');

        return $slug !== '' ? $slug : 'item';
    }

    public static function ensureUnique(string $baseSlug, callable $exists): string
    {
        $slug = $baseSlug;
        $suffix = 2;

        while ($exists($slug)) {
            $slug = $baseSlug . '-' . $suffix;
            ++$suffix;
        }

        return $slug;
    }
}
