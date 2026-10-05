<?php

namespace app\services\import\catalog;

class CatalogPriceDisplayFormatter
{
    public static function formatRubles(int $amount): string
    {
        return number_format($amount, 0, '', ' ') . ' ₽';
    }
}
