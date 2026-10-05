<?php

namespace tests\unit\services;

use app\models\Order;
use app\services\order\OrderUiMapper;
use Codeception\Test\Unit;

class OrderUiMapperTest extends Unit
{
    public function testUiStatusMapping(): void
    {
        $this->assertSame('processing', OrderUiMapper::uiStatus(Order::STATUS_NEW));
        $this->assertSame('processing', OrderUiMapper::uiStatus(Order::STATUS_CONFIRMED));
        $this->assertSame('in_work', OrderUiMapper::uiStatus(Order::STATUS_PRODUCTION));
        $this->assertSame('in_work', OrderUiMapper::uiStatus(Order::STATUS_READY));
        $this->assertSame('delivery', OrderUiMapper::uiStatus(Order::STATUS_SHIPPING));
        $this->assertSame('completed', OrderUiMapper::uiStatus(Order::STATUS_COMPLETED));
        $this->assertSame('cancelled', OrderUiMapper::uiStatus(Order::STATUS_CANCELLED));
    }

    public function testProgressStepMapping(): void
    {
        $this->assertSame(1, OrderUiMapper::progressStep(Order::STATUS_NEW));
        $this->assertSame(2, OrderUiMapper::progressStep(Order::STATUS_PRODUCTION));
        $this->assertSame(3, OrderUiMapper::progressStep(Order::STATUS_SHIPPING));
        $this->assertSame(4, OrderUiMapper::progressStep(Order::STATUS_COMPLETED));
        $this->assertNull(OrderUiMapper::progressStep(Order::STATUS_CANCELLED));
    }
}
