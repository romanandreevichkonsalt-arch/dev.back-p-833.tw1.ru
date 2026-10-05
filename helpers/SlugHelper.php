<?php

namespace app\helpers;

class SlugHelper
{
    /** @var array<string, string> */
    private const TRANSLIT = [
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd',
        'е' => 'e', 'ё' => 'e', 'ж' => 'zh', 'з' => 'z', 'и' => 'i',
        'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n',
        'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't',
        'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'ts', 'ч' => 'ch',
        'ш' => 'sh', 'щ' => 'sch', 'ъ' => '', 'ы' => 'y', 'ь' => '',
        'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
    ];

    public static function slugify(?string $text): string
    {
        $text = trim((string)$text);
        if ($text === '') {
            return '';
        }

        $text = mb_strtolower($text, 'UTF-8');
        $text = strtr($text, self::TRANSLIT);
        $text = (string)preg_replace('/[^a-z0-9]+/', '-', $text);
        $text = trim($text, '-');

        return substr($text, 0, 64);
    }
}
