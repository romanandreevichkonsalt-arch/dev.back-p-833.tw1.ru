<?php

namespace tests\unit\services;

use app\models\Order;
use app\services\order\OrderPaymentMapper;
use Codeception\Test\Unit;
use Yii;

class OrderPaymentMapperTest extends Unit
{
    protected function _after(): void
    {
        Yii::$app->params['orderCashlessSurchargePercent'] = 0;
        parent::_after();
    }

    public function testPaymentReturnTargetForPendingIsSuccess(): void
    {
        $this->assertSame('success', OrderPaymentMapper::paymentReturnTarget(OrderPaymentMapper::PAYMENT_STATUS_PENDING));
        $this->assertSame('success', OrderPaymentMapper::paymentReturnTarget(OrderPaymentMapper::PAYMENT_STATUS_PAID));
    }

    public function testPaymentLabels(): void
    {
        $this->assertSame('Наличные', OrderPaymentMapper::paymentLabel(OrderPaymentMapper::METHOD_CASH));
        $this->assertSame('Безналичная оплата', OrderPaymentMapper::paymentLabel(OrderPaymentMapper::METHOD_CASHLESS));
        $this->assertSame('Онлайн-оплата', OrderPaymentMapper::paymentLabel(OrderPaymentMapper::METHOD_ONLINE));
    }

    public function testCashlessSurchargeUsesPercentFromParams(): void
    {
        Yii::$app->params['orderCashlessSurchargePercent'] = 5;

        $this->assertSame(5000.0, OrderPaymentMapper::cashlessSurchargeAmount(OrderPaymentMapper::METHOD_CASHLESS, 100_000));
        $this->assertSame(0.0, OrderPaymentMapper::cashlessSurchargeAmount(OrderPaymentMapper::METHOD_CASH, 100_000));
    }

    public function testNormalizeMethod(): void
    {
        $this->assertSame(OrderPaymentMapper::METHOD_CASHLESS, OrderPaymentMapper::normalizeMethod('cashless'));
        $this->assertNull(OrderPaymentMapper::normalizeMethod('card'));
    }
}
