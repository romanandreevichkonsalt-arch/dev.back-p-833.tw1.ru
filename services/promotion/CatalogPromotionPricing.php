<?php

namespace app\services\promotion;

use app\models\CatalogPromotion;

final class CatalogPromotionPricing
{
    public const BADGE_TEXT = 'Акция';
    public const BADGE_VARIANT = 'promotion';

    public static function resolveUnitPrice(?int $retailPrice, CatalogPromotion $promotion): ?int
    {
        return self::resolveUnitPriceByRule(
            $retailPrice,
            (string)$promotion->discount_type,
            (float)$promotion->discount_value,
        );
    }

    public static function resolveUnitPriceByRule(?int $retailPrice, string $discountType, float $discountValue): ?int
    {
        if ($discountType === CatalogPromotion::DISCOUNT_PERCENT) {
            if ($retailPrice === null || $retailPrice <= 0) {
                return null;
            }

            return (int)round($retailPrice * (1 - $discountValue / 100));
        }

        if ($retailPrice === null || $retailPrice <= 0) {
            return null;
        }

        $unitPrice = (int)round($retailPrice - $discountValue);

        return $unitPrice > 0 ? $unitPrice : null;
    }

    public static function lineDiscountFromRetail(?int $retailPrice, CatalogPromotion $promotion, int $quantity): float
    {
        if ($quantity < 1) {
            return 0.0;
        }

        $retailUnit = $retailPrice;
        if ($retailUnit === null || $retailUnit <= 0) {
            return 0.0;
        }

        return self::lineDiscountFromRetailByRule(
            $retailUnit,
            (string)$promotion->discount_type,
            (float)$promotion->discount_value,
            $quantity,
        );
    }

    public static function lineDiscountFromRetailByRule(
        ?int $retailPrice,
        string $discountType,
        float $discountValue,
        int $quantity,
    ): float {
        if ($quantity < 1) {
            return 0.0;
        }

        $retailUnit = $retailPrice;
        if ($retailUnit === null || $retailUnit <= 0) {
            return 0.0;
        }

        $promoUnit = self::resolveUnitPriceByRule($retailUnit, $discountType, $discountValue);
        if ($promoUnit === null) {
            return 0.0;
        }

        $retailLineTotal = round($retailUnit * $quantity, 2);
        $promoLineTotal = round($promoUnit * $quantity, 2);

        return round(max(0, $retailLineTotal - $promoLineTotal), 2);
    }

    /**
     * @return array{text: string, variant: string}
     */
    public static function promotionBadgePayload(): array
    {
        return [
            'text' => self::BADGE_TEXT,
            'variant' => self::BADGE_VARIANT,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function toApiPayload(CatalogPromotion $promotion): array
    {
        return [
            'id' => (int)$promotion->id,
            'title' => (string)$promotion->title,
            'discountType' => (string)$promotion->discount_type,
            'discountValue' => (float)$promotion->discount_value,
            'endsAt' => (string)$promotion->ends_at,
        ];
    }

    /**
     * Процент для UI: персональная скидка дилера или % акции (для fixed_amount — эффективный % от розницы).
     *
     * @param array<string, mixed>|null $catalogPromotion payload из toApiPayload()
     */
    public static function resolveDisplayDiscountPercent(
        ?int $retailPrice,
        ?int $dealerPrice,
        ?array $catalogPromotion,
        ?int $personalDealerDiscountPercent,
    ): ?int {
        if ($catalogPromotion !== null && $catalogPromotion !== []) {
            $discountType = (string)($catalogPromotion['discountType'] ?? '');
            $discountValue = (float)($catalogPromotion['discountValue'] ?? 0);

            if ($discountType === CatalogPromotion::DISCOUNT_PERCENT && $discountValue > 0) {
                return (int)round($discountValue);
            }

            if ($retailPrice !== null && $retailPrice > 0 && $dealerPrice !== null) {
                $effective = (1 - $dealerPrice / $retailPrice) * 100;

                return (int)round(max(0, min(100, $effective)));
            }

            return null;
        }

        return $personalDealerDiscountPercent;
    }
}
