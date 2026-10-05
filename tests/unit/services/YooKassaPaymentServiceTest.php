<?php

namespace tests\unit\services;

use app\models\CartItem;
use app\models\CatalogProduct;
use app\models\Order;
use app\services\order\OrderPaymentMapper;
use app\services\payment\YooKassaClient;
use app\services\payment\YooKassaPaymentService;
use Codeception\Test\Unit;
use Yii;

class YooKassaPaymentServiceTest extends Unit
{
    protected function _before(): void
    {
        parent::_before();
        Yii::$app->db->open();
        Yii::$app->params['yookassa'] = [
            'shopId' => 'test-shop',
            'secretKey' => 'test_secret',
            'returnUrl' => 'https://front.example/cart?fromPayment=1',
        ];
    }

    public function testApplySucceededMarksOrderPaid(): void
    {
        $order = $this->createPendingOrder('pay-ext-1', 'unit-yookassa-cart-1');
        $product = $this->createProduct();
        $this->addGuestCartItem($product, 'unit-yookassa-cart-1', 2);

        $service = new YooKassaPaymentService(new class extends YooKassaClient {
            public function __construct()
            {
            }

            public function isConfigured(): bool
            {
                return true;
            }

            public function getPayment(string $paymentId): array
            {
                return [
                    'id' => $paymentId,
                    'status' => 'succeeded',
                    'metadata' => ['orderNumber' => ''],
                ];
            }
        });

        $updated = $service->applyPaymentState([
            'id' => 'pay-ext-1',
            'status' => 'succeeded',
        ], 'payment.succeeded');

        $this->assertNotNull($updated);
        $this->assertSame(OrderPaymentMapper::PAYMENT_STATUS_PAID, $updated->payment_status);
        $this->assertSame(Order::STATUS_NEW, $updated->status);
        $this->assertNotNull($updated->paid_at);
        $this->assertSame(0, CartItem::find()->where(['session_id' => 'unit-yookassa-cart-1'])->count());

        $order->delete();
    }

    public function testApplyCanceledMarksOrderCancelled(): void
    {
        $order = $this->createPendingOrder('pay-ext-2', 'unit-yookassa-cart-2');
        $product = $this->createProduct();
        $this->addGuestCartItem($product, 'unit-yookassa-cart-2', 1);
        $service = new YooKassaPaymentService(new class extends YooKassaClient {
            public function __construct()
            {
            }

            public function isConfigured(): bool
            {
                return true;
            }
        });

        $updated = $service->applyPaymentState([
            'id' => 'pay-ext-2',
            'status' => 'canceled',
        ], 'payment.canceled');

        $this->assertNotNull($updated);
        $this->assertSame(OrderPaymentMapper::PAYMENT_STATUS_CANCELLED, $updated->payment_status);
        $this->assertSame(Order::STATUS_CANCELLED, $updated->status);
        $this->assertSame(1, CartItem::find()->where(['session_id' => 'unit-yookassa-cart-2'])->count());

        $order->delete();
    }

    public function testReturnUrlPointsToCartByDefault(): void
    {
        $order = $this->createPendingOrder('pay-ext-3', 'unit-yookassa-ret');
        $url = (new YooKassaPaymentService())->buildReturnUrlForOrder($order);
        $this->assertStringContainsString('cart', $url);
        $this->assertStringContainsString('fromPayment=1', $url);
        $this->assertStringContainsString('number=' . rawurlencode((string)$order->number), $url);

        $order->delete();
    }

    public function testSyncOrderPaymentFromProviderMarksPaid(): void
    {
        $order = $this->createPendingOrder('pay-sync-1', 'unit-yookassa-sync-1');
        $service = new YooKassaPaymentService(new class extends YooKassaClient {
            public function __construct()
            {
            }

            public function isConfigured(): bool
            {
                return true;
            }

            public function getPayment(string $paymentId): array
            {
                return [
                    'id' => $paymentId,
                    'status' => 'succeeded',
                    'metadata' => ['orderNumber' => ''],
                ];
            }
        });

        $updated = $service->syncOrderPaymentFromProvider($order);

        $this->assertSame(OrderPaymentMapper::PAYMENT_STATUS_PAID, $updated->payment_status);
        $this->assertSame(Order::STATUS_NEW, $updated->status);

        $order->delete();
    }

    public function testApplySimulatedOutcomeSucceeded(): void
    {
        $order = $this->createPendingOrder('', 'unit-yookassa-sim-ok');
        $product = $this->createProduct();
        $this->addGuestCartItem($product, 'unit-yookassa-sim-ok', 1);

        $updated = (new YooKassaPaymentService())->applySimulatedOutcome($order, 'succeeded');

        $this->assertSame(OrderPaymentMapper::PAYMENT_STATUS_PAID, $updated->payment_status);
        $this->assertStringStartsWith('test-sim-', (string)$updated->payment_external_id);
        $this->assertSame(0, CartItem::find()->where(['session_id' => 'unit-yookassa-sim-ok'])->count());

        $order->delete();
    }

    private function createPendingOrder(string $externalId, string $sessionId): Order
    {
        $order = new Order([
            'number' => Order::generateNumber(),
            'customer_name' => 'Guest',
            'customer_phone' => '+79991112233',
            'status' => Order::STATUS_PENDING_PAYMENT,
            'payment_method' => OrderPaymentMapper::METHOD_ONLINE,
            'payment_status' => OrderPaymentMapper::PAYMENT_STATUS_PENDING,
            'payment_provider' => YooKassaPaymentService::PROVIDER,
            'payment_external_id' => $externalId,
            'cashless_surcharge_amount' => 0,
            'subtotal_amount' => 1000,
            'promo_discount_amount' => 0,
            'cashback_used_amount' => 0,
            'total_amount' => 1000,
            'session_id' => $sessionId,
        ]);
        $order->save(false);

        return $order;
    }

    private function createProduct(): CatalogProduct
    {
        $product = new CatalogProduct([
            'slug' => 'yookassa-cart-test-' . substr(md5((string)microtime(true)), 0, 8),
            'title' => 'Test',
            'href' => '/product/yookassa-test',
            'price_display' => '10 000 ₽',
            'price_amount' => 10000,
            'is_active' => true,
            'sort_order' => 0,
        ]);
        $product->save(false);

        return $product;
    }

    private function addGuestCartItem(CatalogProduct $product, string $sessionId, int $quantity): void
    {
        $item = new CartItem([
            'session_id' => $sessionId,
            'catalog_product_id' => (int)$product->id,
            'quantity' => $quantity,
        ]);
        $item->save(false);
    }
}
