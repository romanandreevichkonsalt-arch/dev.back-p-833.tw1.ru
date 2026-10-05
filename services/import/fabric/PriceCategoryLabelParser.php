<?php

namespace app\services\import\fabric;

class PriceCategoryLabelParser
{
    /**
     * @return array{number:int,label:string,price_min:?int,price_max:?int}|null
     */
    public static function parse(string $value): ?array
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (!preg_match('/^(\d+)/u', $value, $matches)) {
            return null;
        }

        $number = (int)$matches[1];
        $priceMin = null;
        $priceMax = null;

        if (preg_match('/от\s+(\d+)/ui', $value, $minMatch) === 1) {
            $priceMin = (int)$minMatch[1];
        }
        if (preg_match('/до\s+(\d+)/ui', $value, $maxMatch) === 1) {
            $priceMax = (int)$maxMatch[1];
        }

        return [
            'number' => $number,
            'label' => self::format($number, $priceMin, $priceMax),
            'price_min' => $priceMin,
            'price_max' => $priceMax,
        ];
    }

    public static function format(int $number, ?int $priceMin, ?int $priceMax): string
    {
        if ($priceMin !== null && $priceMax !== null) {
            return sprintf('%d кат (от %d до %d руб)', $number, $priceMin, $priceMax);
        }
        if ($priceMax !== null) {
            return sprintf('%d кат (до %d руб)', $number, $priceMax);
        }
        if ($priceMin !== null) {
            return sprintf('%d кат (от %d руб)', $number, $priceMin);
        }

        return 'Категория ' . $number;
    }

    public static function extractCategoryNumber(string $value): ?int
    {
        $parsed = self::parse($value);

        return $parsed['number'] ?? null;
    }
}
