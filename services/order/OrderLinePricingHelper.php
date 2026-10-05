<?php

namespace app\services\order;

use app\models\OrderItem;

final class OrderLinePricingHelper
{
    public static function retailUnitPrice(OrderItem $item): float
    {
        if ($item->retail_unit_price !== null && $item->retail_unit_price !== '') {
            return (float)$item->retail_unit_price;
        }

        return (float)$item->unit_price;
    }

    public static function retailLineTotal(OrderItem $item): float
    {
        return round(self::retailUnitPrice($item) * (int)$item->quantity, 2);
    }

    public static function dealerUnitPrice(OrderItem $item): ?float
    {
        if ($item->dealer_unit_price === null || $item->dealer_unit_price === '') {
            return null;
        }

        return (float)$item->dealer_unit_price;
    }

    public static function dealerLineTotal(OrderItem $item): float
    {
        $dealerUnit = self::dealerUnitPrice($item);

        return round(($dealerUnit ?? (float)$item->unit_price) * (int)$item->quantity, 2);
    }

    public static function dealerDiscountAmount(OrderItem $item): float
    {
        return round(max(0, self::retailLineTotal($item) - (float)$item->line_total), 2);
    }

    /**
     * @param list<OrderItem> $items
     */
    public static function retailSubtotal(array $items): float
    {
        $sum = 0.0;
        foreach ($items as $item) {
            $sum += self::retailLineTotal($item);
        }

        return round($sum, 2);
    }

    /**
     * @param list<OrderItem> $items
     */
    public static function dealerDiscountTotal(array $items): float
    {
        $sum = 0.0;
        foreach ($items as $item) {
            $sum += self::dealerDiscountAmount($item);
        }

        return round($sum, 2);
    }

    /**
     * @param list<OrderItem> $items
     */
    public static function catalogPromotionDiscountTotal(array $items): float
    {
        $sum = 0.0;
        foreach ($items as $item) {
            $sum += (float)$item->catalog_promotion_discount;
        }

        return round($sum, 2);
    }

    /**
     * @param list<OrderItem> $items
     */
    public static function dealerPersonalDiscountTotal(array $items): float
    {
        $sum = 0.0;
        foreach ($items as $item) {
            if ((bool)$item->has_catalog_promotion) {
                continue;
            }
            $sum += self::dealerDiscountAmount($item);
        }

        return round($sum, 2);
    }
}
