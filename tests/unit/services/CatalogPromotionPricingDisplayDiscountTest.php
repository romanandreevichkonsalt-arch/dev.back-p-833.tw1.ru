<?php

namespace tests\unit\services;

use app\models\CatalogPromotion;
use app\services\promotion\CatalogPromotionPricing;
use Codeception\Test\Unit;

class CatalogPromotionPricingDisplayDiscountTest extends Unit
{
    public function testPersonalDiscountWhenNoPromotion(): void
    {
        $percent = CatalogPromotionPricing::resolveDisplayDiscountPercent(
            100000,
            90000,
            null,
            10,
        );

        $this->assertSame(10, $percent);
    }

    public function testPromotionPercentDiscount(): void
    {
        $percent = CatalogPromotionPricing::resolveDisplayDiscountPercent(
            100000,
            90000,
            [
                'discountType' => CatalogPromotion::DISCOUNT_PERCENT,
                'discountValue' => 15,
            ],
            5,
        );

        $this->assertSame(15, $percent);
    }

    public function testPromotionFixedAmountUsesEffectivePercent(): void
    {
        $percent = CatalogPromotionPricing::resolveDisplayDiscountPercent(
            100000,
            85000,
            [
                'discountType' => CatalogPromotion::DISCOUNT_FIXED,
                'discountValue' => 15000,
            ],
            5,
        );

        $this->assertSame(15, $percent);
    }
}
