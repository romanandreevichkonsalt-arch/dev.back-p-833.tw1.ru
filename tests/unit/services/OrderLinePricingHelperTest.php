<?php

namespace tests\unit\services;

use app\models\OrderItem;
use app\services\order\OrderLinePricingHelper;
use Codeception\Test\Unit;

class OrderLinePricingHelperTest extends Unit
{
    public function testRetailAndDealerDiscountFromSnapshot(): void
    {
        $item = new OrderItem([
            'quantity' => 2,
            'retail_unit_price' => 100000,
            'dealer_unit_price' => 80000,
            'unit_price' => 80000,
            'line_total' => 160000,
        ]);

        $this->assertSame(100000.0, OrderLinePricingHelper::retailUnitPrice($item));
        $this->assertSame(200000.0, OrderLinePricingHelper::retailLineTotal($item));
        $this->assertSame(80000.0, OrderLinePricingHelper::dealerUnitPrice($item));
        $this->assertSame(40000.0, OrderLinePricingHelper::dealerDiscountAmount($item));
    }

    public function testRetailSubtotalAggregatesLines(): void
    {
        $items = [
            new OrderItem([
                'quantity' => 1,
                'retail_unit_price' => 50000,
                'unit_price' => 50000,
                'line_total' => 50000,
            ]),
            new OrderItem([
                'quantity' => 1,
                'retail_unit_price' => 100000,
                'dealer_unit_price' => 90000,
                'unit_price' => 90000,
                'line_total' => 90000,
            ]),
        ];

        $this->assertSame(150000.0, OrderLinePricingHelper::retailSubtotal($items));
        $this->assertSame(10000.0, OrderLinePricingHelper::dealerDiscountTotal($items));
    }
}
