<?php

namespace tests\unit\services;

use app\models\DealerPromoGrant;
use app\services\order\OrderLinePricingAllocator;
use Codeception\Test\Unit;

class OrderLinePricingAllocatorTest extends Unit
{
    public function testAllocatesPromoOnlyOnScopedModelLines(): void
    {
        $lines = [
            ['lineTotal' => 100000.0, 'catalogModelId' => 10],
            ['lineTotal' => 50000.0, 'catalogModelId' => 20],
        ];

        $grant = new DealerPromoGrant([
            'discount_percent' => 10,
            'catalog_model_id' => 10,
        ]);

        $allocated = (new OrderLinePricingAllocator())->allocate(
            $lines,
            150000.0,
            10000.0,
            0.0,
            $grant,
        );

        $this->assertSame(10000.0, $allocated[0]['promoDiscountAmount']);
        $this->assertSame(0.0, $allocated[1]['promoDiscountAmount']);
        $this->assertSame(90000.0, $allocated[0]['paidLineTotal']);
        $this->assertSame(50000.0, $allocated[1]['paidLineTotal']);
    }

    public function testAllocatesCashbackProportionallyWhenNoPromo(): void
    {
        $lines = [
            ['lineTotal' => 20000.0, 'catalogModelId' => 1],
            ['lineTotal' => 80000.0, 'catalogModelId' => 2],
        ];

        $allocated = (new OrderLinePricingAllocator())->allocate(
            $lines,
            100000.0,
            0.0,
            5000.0,
            null,
        );

        $this->assertSame(1000.0, $allocated[0]['cashbackUsedAmount']);
        $this->assertSame(4000.0, $allocated[1]['cashbackUsedAmount']);
        $this->assertSame(19000.0, $allocated[0]['paidLineTotal']);
        $this->assertSame(76000.0, $allocated[1]['paidLineTotal']);
    }

    public function testCashbackDoesNotApplyToCatalogPromotionLines(): void
    {
        $lines = [
            ['lineTotal' => 200_000.0, 'catalogModelId' => 1, 'hasCatalogPromotion' => true],
            ['lineTotal' => 100_000.0, 'catalogModelId' => 2, 'hasCatalogPromotion' => false],
        ];

        $allocated = (new OrderLinePricingAllocator())->allocate(
            $lines,
            300_000.0,
            0.0,
            50_000.0,
            null,
        );

        $this->assertSame(0.0, $allocated[0]['cashbackUsedAmount']);
        $this->assertSame(50_000.0, $allocated[1]['cashbackUsedAmount']);
        $this->assertSame(200_000.0, $allocated[0]['paidLineTotal']);
        $this->assertSame(50_000.0, $allocated[1]['paidLineTotal']);
    }

    public function testPaidLineTotalEqualsLineTotalWithoutDiscounts(): void
    {
        $lines = [
            ['lineTotal' => 120000.0, 'catalogModelId' => null],
        ];

        $allocated = (new OrderLinePricingAllocator())->allocate(
            $lines,
            120000.0,
            0.0,
            0.0,
            null,
        );

        $this->assertSame(120000.0, $allocated[0]['paidLineTotal']);
    }
}
