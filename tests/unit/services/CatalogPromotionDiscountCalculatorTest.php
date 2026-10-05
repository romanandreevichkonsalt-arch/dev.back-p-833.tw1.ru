<?php

namespace tests\unit\services;

use app\models\CatalogPromotion;
use app\services\promotion\CatalogPromotionPricing;
use Codeception\Test\Unit;

class CatalogPromotionDiscountCalculatorTest extends Unit
{
    public function testFixedAmountIsSubtractedFromRetail(): void
    {
        $this->assertSame(
            80_000,
            CatalogPromotionPricing::resolveUnitPriceByRule(100_000, CatalogPromotion::DISCOUNT_FIXED, 20_000),
        );
        $this->assertSame(20_000.0, CatalogPromotionPricing::lineDiscountFromRetailByRule(
            100_000,
            CatalogPromotion::DISCOUNT_FIXED,
            20_000,
            1,
        ));
    }

    public function testPercentDiscountFromRetail(): void
    {
        $this->assertSame(
            85_000,
            CatalogPromotionPricing::resolveUnitPriceByRule(100_000, CatalogPromotion::DISCOUNT_PERCENT, 15),
        );
        $this->assertSame(15_000.0, CatalogPromotionPricing::lineDiscountFromRetailByRule(
            100_000,
            CatalogPromotion::DISCOUNT_PERCENT,
            15,
            1,
        ));
    }
}
