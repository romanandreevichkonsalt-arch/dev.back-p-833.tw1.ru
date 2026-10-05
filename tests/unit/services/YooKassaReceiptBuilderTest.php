<?php

namespace tests\unit\services;

use app\models\Order;
use app\models\OrderItem;
use app\services\order\OrderPaymentMapper;
use app\services\payment\YooKassaPaymentService;
use Codeception\Test\Unit;
use ReflectionMethod;
use Yii;

class YooKassaReceiptBuilderTest extends Unit
{
    protected function _before(): void
    {
        parent::_before();
        Yii::$app->params['yookassa'] = [
            'shopId' => 'test-shop',
            'secretKey' => 'test_secret',
            'sendReceipt' => true,
            'receiptVatCode' => 1,
            'receiptTaxSystemCode' => 1,
            'receiptMeasure' => 'piece',
            'receiptPaymentMode' => 'full_prepayment',
            'receiptInternet' => true,
            'receiptTimezone' => 3,
            'receiptFallbackEmailDomain' => 'noreply.dev.front-p-833.tw1.ru',
        ];
    }

    public function testBuildReceiptIncludesFfdFields(): void
    {
        $order = new Order([
            'number' => 'ORD-UNIT-RECEIPT',
            'customer_name' => 'Guest',
            'customer_phone' => '+79991234567',
            'customer_email' => 'guest@example.com',
            'status' => Order::STATUS_PENDING_PAYMENT,
            'payment_method' => OrderPaymentMapper::METHOD_ONLINE,
            'total_amount' => 1000.0,
            'cashless_surcharge_amount' => 0,
        ]);

        $item = new OrderItem([
            'product_title' => 'Диван',
            'quantity' => 1,
            'paid_line_total' => 1000.0,
            'line_total' => 1000.0,
        ]);
        $order->populateRelation('items', [$item]);

        $method = new ReflectionMethod(YooKassaPaymentService::class, 'buildReceipt');
        $method->setAccessible(true);
        $receipt = $method->invoke(new YooKassaPaymentService(), $order);

        $this->assertIsArray($receipt);
        $this->assertSame('guest@example.com', $receipt['customer']['email']);
        $this->assertSame('79991234567', $receipt['customer']['phone']);
        $this->assertSame(1, $receipt['tax_system_code']);
        $this->assertSame('piece', $receipt['items'][0]['measure']);
        $this->assertSame('full_prepayment', $receipt['items'][0]['payment_mode']);
        $this->assertSame('true', $receipt['internet']);
        $this->assertSame(3, $receipt['timezone']);
    }

    public function testReceiptUsesFallbackEmailWhenOrderHasNoEmail(): void
    {
        $order = new Order([
            'number' => 'ORD-UNIT-NO-EMAIL',
            'customer_phone' => '+79991234567',
            'customer_email' => null,
            'total_amount' => 1000.0,
            'cashless_surcharge_amount' => 0,
        ]);
        $order->populateRelation('items', []);

        $method = new ReflectionMethod(YooKassaPaymentService::class, 'buildReceipt');
        $method->setAccessible(true);
        $receipt = $method->invoke(new YooKassaPaymentService(), $order);

        $this->assertSame(
            'guest+79991234567@noreply.dev.front-p-833.tw1.ru',
            $receipt['customer']['email'],
        );
    }

    public function testReceiptUsesDestinationEmailForAllOrders(): void
    {
        Yii::$app->params['yookassa']['receiptDestinationEmail'] = 'tania22gerasimenko@gmail.com';

        $order = new Order([
            'number' => 'ORD-UNIT-DEST',
            'customer_phone' => '+79991234567',
            'customer_email' => 'other@example.com',
            'total_amount' => 500.0,
            'cashless_surcharge_amount' => 0,
        ]);
        $order->populateRelation('items', []);

        $method = new ReflectionMethod(YooKassaPaymentService::class, 'buildReceipt');
        $method->setAccessible(true);
        $receipt = $method->invoke(new YooKassaPaymentService(), $order);

        $this->assertSame('tania22gerasimenko@gmail.com', $receipt['customer']['email']);
    }

    public function testFormatReceiptPhoneDigitsNormalizesRussianNumbers(): void
    {
        $method = new ReflectionMethod(YooKassaPaymentService::class, 'formatReceiptPhoneDigits');
        $method->setAccessible(true);
        $service = new YooKassaPaymentService();

        $this->assertSame('79991234567', $method->invoke($service, '+79991234567'));
        $this->assertSame('79991234567', $method->invoke($service, '89991234567'));
    }
}
