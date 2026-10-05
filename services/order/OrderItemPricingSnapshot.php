<?php

namespace app\services\order;

use app\models\OrderItem;

final class OrderItemPricingSnapshot
{
    /**
     * @return array<string, mixed>
     */
    public static function toOrderLineApiFields(OrderItem $item): array
    {
        $hasCatalogPromotion = (bool)$item->has_catalog_promotion;
        $catalogPromotionDiscount = round((float)$item->catalog_promotion_discount, 2);
        $retailLineTotal = OrderLinePricingHelper::retailLineTotal($item);
        $lineTotal = round((float)$item->line_total, 2);
        $totalDealerSideDiscount = round(max(0, $retailLineTotal - $lineTotal), 2);

        $personalDealerDiscount = $hasCatalogPromotion
            ? 0.0
            : $totalDealerSideDiscount;

        $payload = [
            'hasCatalogPromotion' => $hasCatalogPromotion,
            'cashbackEligible' => !$hasCatalogPromotion,
            'catalogPromotionDiscount' => $catalogPromotionDiscount,
            'dealerPersonalDiscountAmount' => $personalDealerDiscount,
            'dealerDiscountAmount' => $totalDealerSideDiscount,
        ];

        if ($item->dealer_discount_percent !== null && $item->dealer_discount_percent !== '') {
            $payload['dealerDiscountPercent'] = (int)$item->dealer_discount_percent;
        }

        $promotion = self::decodeCatalogPromotion($item->catalog_promotion_snapshot);
        if ($promotion !== null) {
            $payload['catalogPromotion'] = $promotion;
        }

        return $payload;
    }

    /**
     * @param array<string, mixed>|null $promotion
     */
    public static function encodeCatalogPromotion(?array $promotion): ?string
    {
        if ($promotion === null || $promotion === []) {
            return null;
        }

        $encoded = json_encode($promotion, JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            return null;
        }

        return $encoded;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function decodeCatalogPromotion(?string $raw): ?array
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }
}
