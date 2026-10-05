<?php

namespace tests\unit\services;

use app\models\OrderItem;
use app\services\order\OrderItemPricingSnapshot;
use Codeception\Test\Unit;

class OrderItemPricingSnapshotTest extends Unit
{
    public function testPersonalDealerDiscountFields(): void
    {
        $item = new OrderItem([
            'quantity' => 1,
            'retail_unit_price' => 100000,
            'unit_price' => 80000,
            'line_total' => 80000,
            'dealer_unit_price' => 80000,
            'dealer_discount_percent' => 20,
            'has_catalog_promotion' => false,
        ]);

        $fields = OrderItemPricingSnapshot::toOrderLineApiFields($item);

        $this->assertFalse($fields['hasCatalogPromotion']);
        $this->assertSame(20000.0, $fields['dealerPersonalDiscountAmount']);
        $this->assertSame(20000.0, $fields['dealerDiscountAmount']);
        $this->assertSame(20, $fields['dealerDiscountPercent']);
    }

    public function testCatalogPromotionFields(): void
    {
        $snapshot = OrderItemPricingSnapshot::encodeCatalogPromotion([
            'id' => 5,
            'title' => 'Весна',
            'badgeText' => 'Акция',
            'discountType' => 'percent',
            'discountValue' => 15,
        ]);

        $item = new OrderItem([
            'quantity' => 2,
            'retail_unit_price' => 50000,
            'unit_price' => 42500,
            'line_total' => 85000,
            'dealer_unit_price' => 42500,
            'has_catalog_promotion' => true,
            'catalog_promotion_discount' => 15000,
            'catalog_promotion_snapshot' => $snapshot,
        ]);

        $fields = OrderItemPricingSnapshot::toOrderLineApiFields($item);

        $this->assertTrue($fields['hasCatalogPromotion']);
        $this->assertSame(15000.0, $fields['catalogPromotionDiscount']);
        $this->assertSame(0.0, $fields['dealerPersonalDiscountAmount']);
        $this->assertSame(15000.0, $fields['dealerDiscountAmount']);
        $this->assertSame('Весна', $fields['catalogPromotion']['title']);
    }
}
