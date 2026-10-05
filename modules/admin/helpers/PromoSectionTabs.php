<?php

namespace app\modules\admin\helpers;

class PromoSectionTabs
{
    public const TAB_PROMO_CODES = 'promo-codes';
    public const TAB_BANNERS = 'banners';
    public const TAB_SALES = 'sales';

    /**
     * @return array<string, array{label: string, url: array<int|string, int|string|null>|string}>
     */
    public static function definitions(): array
    {
        return [
            self::TAB_PROMO_CODES => [
                'label' => 'Промокоды',
                'url' => ['/admin/promo-code/index', 'tab' => self::TAB_PROMO_CODES],
            ],
            self::TAB_BANNERS => [
                'label' => 'Баннеры акций',
                'url' => ['/admin/promo-code/index', 'tab' => self::TAB_BANNERS],
            ],
            self::TAB_SALES => [
                'label' => 'Акции',
                'url' => ['/admin/promo-code/index', 'tab' => self::TAB_SALES],
            ],
        ];
    }

    public static function resolve(?string $tab): string
    {
        $tab = $tab ?? self::TAB_PROMO_CODES;
        $allowed = [
            self::TAB_PROMO_CODES,
            self::TAB_BANNERS,
            self::TAB_SALES,
        ];

        return in_array($tab, $allowed, true) ? $tab : self::TAB_PROMO_CODES;
    }
}
