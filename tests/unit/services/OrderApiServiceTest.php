<?php

namespace tests\unit\services;

use app\exceptions\ApiValidationException;
use app\models\CartItem;
use app\models\CatalogProduct;
use app\models\DealerCartCheckout;
use app\models\DealerCashbackAccount;
use app\models\DealerProfile;
use app\models\DealerPromoGrant;
use app\models\Order;
use app\models\User;
use app\models\OrderItem;
use app\services\guest\ApiOwnerContext;
use app\services\order\OrderApiService;
use app\services\order\OrderPaymentMapper;
use app\services\payment\YooKassaPaymentService;
use Codeception\Test\Unit;
use Yii;
use yii\web\ForbiddenHttpException;

class OrderApiServiceTest extends Unit
{
    private const USERNAME = 'unit-order-api-dealer';
    private const SESSION_ID = 'unit-order-api-session';

    private function orderService(): OrderApiService
    {
        return new OrderApiService(yooKassaPaymentService: new class extends YooKassaPaymentService {
            public function __construct()
            {
            }

            public function isConfigured(): bool
            {
                return true;
            }

            public function createRedirectPayment(\app\models\Order $order): array
            {
                $order->payment_provider = self::PROVIDER;
                $order->payment_external_id = 'test-payment-' . $order->id;
                $order->payment_status = OrderPaymentMapper::PAYMENT_STATUS_PENDING;
                $order->payment_method = OrderPaymentMapper::METHOD_ONLINE;
                $order->save(false, [
                    'payment_provider',
                    'payment_external_id',
                    'payment_status',
                    'payment_method',
                    'updated_at',
                ]);

                return [
                    'externalId' => (string)$order->payment_external_id,
                    'confirmationUrl' => 'https://yookassa.test/checkout/' . $order->number,
                    'returnUrl' => 'https://front.example/cart?fromPayment=1&number=' . rawurlencode((string)$order->number),
                    'status' => 'pending',
                    'receiptIncluded' => true,
                    'receiptSkipReason' => null,
                ];
            }
        });
    }

    protected function _before(): void
    {
        parent::_before();
        Yii::$app->db->open();

        CartItem::deleteAll(['session_id' => self::SESSION_ID]);
        Order::deleteAll(['session_id' => self::SESSION_ID]);
        CatalogProduct::deleteAll(['like', 'slug', 'order-api-test-%', false]);

        $user = User::findOne(['username' => self::USERNAME]);
        if ($user !== null) {
            DealerCartCheckout::deleteAll(['user_id' => (int)$user->id]);
            DealerPromoGrant::deleteAll(['user_id' => (int)$user->id]);
            DealerCashbackAccount::deleteAll(['user_id' => (int)$user->id]);
            DealerProfile::deleteAll(['user_id' => (int)$user->id]);
            CartItem::deleteAll(['user_id' => (int)$user->id]);
            Order::deleteAll(['user_id' => (int)$user->id]);
            $user->delete();
        }
    }

    public function testGuestCreatesOrderFromPayloadWithoutCart(): void
    {
        $product = $this->createProduct();
        $owner = new ApiOwnerContext(null, self::SESSION_ID);

        $order = $this->orderService()->createFromCart($owner, [
            'customerName' => 'Гость',
            'customerPhone' => '+79991112233',
            'customerEmail' => 'guest-unit@test.example',
            'items' => [
                ['productId' => $product->slug, 'quantity' => 2],
            ],
        ]);

        $this->assertSame(2, $order['items'][0]['quantity']);
        $this->assertSame(20000.0, $order['items'][0]['lineTotal']);
        $this->assertSame(20000.0, $order['items'][0]['paidLineTotal']);
        $this->assertSame(0.0, $order['items'][0]['promoDiscountAmount']);
        $this->assertNull($order['items'][0]['comment']);
        $this->assertSame(Order::STATUS_PENDING_PAYMENT, $order['status']);
        $this->assertSame(OrderPaymentMapper::METHOD_ONLINE, $order['paymentMethod']);
        $this->assertSame(OrderPaymentMapper::PAYMENT_STATUS_PENDING, $order['paymentStatus']);
        $this->assertSame('https://yookassa.test/checkout/' . $order['number'], $order['paymentConfirmationUrl']);
        $this->assertSame(0, CartItem::find()->where(['session_id' => self::SESSION_ID])->count());
    }

    public function testGuestKeepsCartUntilPaymentSucceeded(): void
    {
        $product = $this->createProduct();
        $this->addGuestCartItem($product, 1, null);
        $owner = new ApiOwnerContext(null, self::SESSION_ID);

        $order = $this->orderService()->createFromCart($owner, [
            'customerName' => 'Гость',
            'customerPhone' => '+79991112233',
            'customerEmail' => 'guest-unit@test.example',
        ]);

        $this->assertSame(Order::STATUS_PENDING_PAYMENT, $order['status']);
        $this->assertSame(1, CartItem::find()->where(['session_id' => self::SESSION_ID])->count());
        $this->assertArrayHasKey('paymentReturnUrl', $order);
    }

    public function testGuestPaymentStatusPublicWithoutSession(): void
    {
        $product = $this->createProduct();
        $owner = new ApiOwnerContext(null, self::SESSION_ID);

        $created = $this->orderService()->createFromCart($owner, [
            'customerName' => 'Гость',
            'customerPhone' => '+79991112233',
            'customerEmail' => 'guest-unit@test.example',
            'items' => [
                ['productId' => $product->slug, 'quantity' => 1],
            ],
        ]);

        $status = (new OrderApiService())->getGuestPaymentStatusPublic((string)$created['number']);

        $this->assertSame($created['number'], $status['number']);
        $this->assertSame('success', $status['paymentReturnTarget']);
        $this->assertSame(OrderPaymentMapper::PAYMENT_STATUS_PENDING, $status['paymentStatus']);
        $this->assertArrayNotHasKey('customerName', $status);
        $this->assertArrayNotHasKey('items', $status);
    }

    public function testDealerCreatesOrderFromPayloadWithComment(): void
    {
        $dealer = $this->createDealer();
        $product = $this->createProduct();
        $owner = new ApiOwnerContext((int)$dealer->id, null);

        $order = $this->orderService()->createFromCart($owner, [
            'customerName' => 'Дилер',
            'customerPhone' => '+79991112233',
            'items' => [
                ['productId' => $product->slug, 'quantity' => 1, 'comment' => 'Срок 2 недели'],
            ],
        ]);

        $this->assertSame('Срок 2 недели', $order['items'][0]['comment']);
    }

    public function testRejectsEmptyCartAndMissingItems(): void
    {
        $owner = new ApiOwnerContext(null, self::SESSION_ID);

        $this->expectException(ApiValidationException::class);
        $this->expectExceptionMessage('Укажите позиции заказа');

        $this->orderService()->createFromCart($owner, [
            'customerName' => 'Гость',
            'customerPhone' => '+79991112233',
            'customerEmail' => 'guest-unit@test.example',
        ]);
    }

    public function testRejectsMixedItemsWithAndWithoutQuantity(): void
    {
        $product = $this->createProduct();
        $owner = new ApiOwnerContext(null, self::SESSION_ID);

        $this->expectException(ApiValidationException::class);
        $this->orderService()->createFromCart($owner, [
            'customerName' => 'Гость',
            'customerPhone' => '+79991112233',
            'customerEmail' => 'guest-unit@test.example',
            'items' => [
                ['productId' => $product->slug, 'quantity' => 1],
                ['productId' => $product->slug, 'comment' => 'Без quantity'],
            ],
        ]);
    }

    public function testGuestRejectsItemCommentsInPayload(): void
    {
        $product = $this->createProduct();
        $this->addGuestCartItem($product, 1, null);

        $owner = new ApiOwnerContext(null, self::SESSION_ID);

        $this->expectException(ForbiddenHttpException::class);
        $this->orderService()->createFromCart($owner, [
            'customerName' => 'Гость',
            'customerPhone' => '+79991112233',
            'customerEmail' => 'guest-unit@test.example',
            'items' => [
                ['productId' => $product->slug, 'comment' => 'Нельзя'],
            ],
        ]);
    }

    public function testDealerRejectsOrderLevelComment(): void
    {
        $dealer = $this->createDealer();
        $product = $this->createProduct();
        $this->addUserCartItem($dealer, $product, 1, 'Из корзины');

        $owner = new ApiOwnerContext((int)$dealer->id, null);

        $this->expectException(ApiValidationException::class);
        $this->orderService()->createFromCart($owner, [
            'customerName' => 'Дилер',
            'customerPhone' => '+79991112233',
            'comment' => 'На весь заказ',
        ]);
    }

    public function testDealerUsesItemCommentOverrideOnCheckout(): void
    {
        $dealer = $this->createDealer();
        $product = $this->createProduct();
        $this->addUserCartItem($dealer, $product, 1, 'Из корзины');

        $owner = new ApiOwnerContext((int)$dealer->id, null);

        $order = $this->orderService()->createFromCart($owner, [
            'customerName' => 'Дилер',
            'customerPhone' => '+79991112233',
            'items' => [
                ['productId' => $product->slug, 'comment' => 'При оформлении'],
            ],
        ]);

        $this->assertSame('При оформлении', $order['items'][0]['comment']);
        $this->assertNull($order['comment']);
        $this->assertSame('processing', $order['uiStatus']);
        $this->assertSame(1, $order['progressStep']);
    }

    public function testDealerCheckoutStoresPaymentMethod(): void
    {
        $dealer = $this->createDealer();
        $product = $this->createProduct();
        $this->addUserCartItem($dealer, $product, 1, null);

        $owner = new ApiOwnerContext((int)$dealer->id, null);

        Yii::$app->params['orderCashlessSurchargePercent'] = 5;
        $order = $this->orderService()->createFromCart($owner, [
            'customerName' => 'Дилер',
            'customerPhone' => '+79991112233',
            'paymentMethod' => 'cashless',
        ]);

        $this->assertSame('cashless', $order['paymentMethod']);
        $this->assertSame('Безналичная оплата', $order['paymentLabel']);
        $this->assertSame(500.0, $order['cashlessSurchargeAmount']);
        $this->assertSame(10500.0, $order['totalAmount']);
        $this->assertIsArray($order['documents']);
        $this->assertSame([], $order['documents']);
    }

    public function testDealerOrderSnapshotsRetailAndDealerPrices(): void
    {
        $dealer = $this->createDealer(20.0);
        $product = $this->createProduct(100000);
        $owner = new ApiOwnerContext((int)$dealer->id, null);

        $order = $this->orderService()->createFromCart($owner, [
            'customerName' => 'Дилер',
            'customerPhone' => '+79991112233',
            'items' => [
                ['productId' => $product->slug, 'quantity' => 1],
            ],
        ]);

        $line = $order['items'][0];
        $this->assertSame(100000.0, $line['retailPrice']);
        $this->assertSame(100000.0, $line['retailLineTotal']);
        $this->assertSame(80000, $line['dealerPrice']);
        $this->assertSame(20000.0, $line['dealerDiscountAmount']);
        $this->assertSame(20000.0, $line['dealerPersonalDiscountAmount']);
        $this->assertSame(20, $line['dealerDiscountPercent']);
        $this->assertFalse($line['hasCatalogPromotion']);
        $this->assertSame(80000.0, $line['lineTotal']);
        $this->assertSame(80000.0, $line['paidLineTotal']);
        $this->assertSame(100000.0, $order['retailSubtotalAmount']);
        $this->assertSame(20000.0, $order['dealerDiscountAmount']);
        $this->assertSame(20000.0, $order['dealerPersonalDiscountAmount']);
        $this->assertSame(0.0, $order['catalogPromotionDiscountAmount']);
        $this->assertSame(80000.0, $order['subtotalAmount']);
        $this->assertSame(20000.0, $order['discounts']['dealer']['amount']);
        $this->assertSame(20000.0, $order['discounts']['dealer']['personalAmount']);
        $this->assertNull($order['discounts']['promotion']);
        $this->assertNull($order['discounts']['promo']);
    }

    public function testDealerOrderCapsCashbackByBalance(): void
    {
        $profileSchema = Yii::$app->db->schema->getTableSchema('{{%dealer_profiles}}');
        if ($profileSchema === null || $profileSchema->getColumn('personal_discount_percent') === null) {
            $this->markTestSkipped('Test DB schema is missing dealer_profiles.personal_discount_percent.');
        }

        $dealer = $this->createDealer();
        $product = $this->createProduct(10000);
        $this->createCashbackAccount((int)$dealer->id, 3000.0);

        $checkout = new DealerCartCheckout([
            'user_id' => (int)$dealer->id,
            'promo_grant_id' => null,
            'cashback_amount' => 10000.0,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $checkout->save(false);

        Yii::$app->user->setIdentity($dealer);
        $owner = new ApiOwnerContext((int)$dealer->id, null);
        $order = $this->orderService()->createFromCart($owner, [
            'customerName' => 'Дилер',
            'customerPhone' => '+79991112233',
            'items' => [
                ['productId' => $product->slug, 'quantity' => 1],
            ],
        ]);

        $this->assertSame(10000.0, $order['subtotalAmount']);
        $this->assertSame(3000.0, $order['cashbackUsedAmount']);
        $this->assertSame(7000.0, $order['totalAmount']);
        $this->assertSame(3000.0, $order['discounts']['cashback']['amount']);
        $this->assertSame(7000.0, $order['items'][0]['paidLineTotal']);
        $this->assertSame(3000.0, $order['items'][0]['cashbackUsedAmount']);

        $account = DealerCashbackAccount::findOne((int)$dealer->id);
        $this->assertNotNull($account);
        $this->assertSame(0.0, (float)$account->balance);
    }

    public function testDealerOrderAppliesPromoAfterDealerDiscount(): void
    {
        $dealer = $this->createDealer(20.0);
        $product = $this->createProduct(100000);
        $grant = new DealerPromoGrant([
            'user_id' => (int)$dealer->id,
            'code' => 'ORDER_PROMO_10',
            'discount_percent' => 10,
            'source' => DealerPromoGrant::SOURCE_NOVELTY,
            'is_active' => true,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $grant->save(false);

        $checkout = new DealerCartCheckout([
            'user_id' => (int)$dealer->id,
            'promo_grant_id' => (int)$grant->id,
            'cashback_amount' => 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $checkout->save(false);

        $owner = new ApiOwnerContext((int)$dealer->id, null);
        $order = $this->orderService()->createFromCart($owner, [
            'customerName' => 'Дилер',
            'customerPhone' => '+79991112233',
            'items' => [
                ['productId' => $product->slug, 'quantity' => 1],
            ],
        ]);

        $this->assertSame(80000.0, $order['subtotalAmount']);
        $this->assertSame(8000.0, $order['promoDiscountAmount']);
        $this->assertSame(72000.0, $order['totalAmount']);
        $this->assertSame(8000.0, $order['discounts']['promo']['amount']);
        $this->assertSame('ORDER_PROMO_10', $order['discounts']['promo']['code']);
        $this->assertSame(72000.0, $order['items'][0]['paidLineTotal']);
        $this->assertSame(8000.0, $order['items'][0]['promoDiscountAmount']);
    }

    public function testListOrdersReturnsSummaryFields(): void
    {
        $dealer = $this->createDealer();
        $product = $this->createProduct();
        $this->addUserCartItem($dealer, $product, 2, null);

        $owner = new ApiOwnerContext((int)$dealer->id, null);
        $created = $this->orderService()->createFromCart($owner, [
            'customerName' => 'Дилер',
            'customerPhone' => '+79991112233',
        ]);

        $items = $this->orderService()->listOrders((int)$dealer->id);
        $this->assertCount(1, $items);
        $summary = $items[0];

        $this->assertSame($created['number'], $summary['number']);
        $this->assertSame('processing', $summary['uiStatus']);
        $this->assertSame(1, $summary['progressStep']);
        $this->assertSame(2, $summary['itemsCount']);
        $this->assertIsArray($summary['images']);
        $this->assertSame(0, $summary['extraCount']);
    }

    public function testFindOrderItemAttachment(): void
    {
        $dealer = $this->createDealer();
        $product = $this->createProduct();
        $this->addUserCartItem($dealer, $product, 1, null);

        $owner = new ApiOwnerContext((int)$dealer->id, null);
        $created = $this->orderService()->createFromCart($owner, [
            'customerName' => 'Дилер',
            'customerPhone' => '+79991112233',
        ]);

        $order = Order::findOne(['number' => $created['number']]);
        $this->assertNotNull($order);

        $item = OrderItem::findOne(['order_id' => (int)$order->id, 'product_sku' => $product->slug]);
        $this->assertNotNull($item);
        $item->attachment_path = 'missing-test.bin';
        $item->attachment_original_name = 'file.pdf';
        $item->save(false, ['attachment_path', 'attachment_original_name']);

        $found = $this->orderService()->findOrderItemAttachment($owner, $created['number'], $product->slug);
        $this->assertSame($product->slug, $found->product_sku);
        $this->assertSame('file.pdf', $found->attachment_original_name);
    }

    private function createProduct(int $priceAmount = 10000): CatalogProduct
    {
        $product = new CatalogProduct([
            'slug' => 'order-api-test-' . substr(md5(uniqid('', true)), 0, 10),
            'title' => 'Товар для заказа',
            'href' => '/product/order-test',
            'price_display' => number_format($priceAmount, 0, '.', ' ') . ' ₽',
            'price_amount' => $priceAmount,
            'is_active' => true,
            'sort_order' => 0,
        ]);
        $product->save(false);

        return $product;
    }

    private function addGuestCartItem(CatalogProduct $product, int $quantity, ?string $comment): void
    {
        $item = new CartItem([
            'session_id' => self::SESSION_ID,
            'catalog_product_id' => (int)$product->id,
            'quantity' => $quantity,
            'comment' => $comment,
        ]);
        $item->save(false);
    }

    private function addUserCartItem(User $user, CatalogProduct $product, int $quantity, ?string $comment): void
    {
        $item = new CartItem([
            'user_id' => (int)$user->id,
            'catalog_product_id' => (int)$product->id,
            'quantity' => $quantity,
            'comment' => $comment,
        ]);
        $item->save(false);
    }

    private function createCashbackAccount(int $userId, float $balance): void
    {
        $account = new DealerCashbackAccount([
            'user_id' => $userId,
            'balance' => $balance,
            'period_total' => 0,
            'period_year_month' => date('Y-m'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $account->save(false);
    }

    private function createDealer(?float $personalDiscountPercent = null): User
    {
        $user = new User([
            'username' => self::USERNAME,
            'type' => User::TYPE_DEALER,
            'phone' => '79990003344',
            'password_hash' => 'x',
        ]);
        $user->save(false);

        $profileAttributes = [
            'user_id' => (int)$user->id,
            'company_name' => 'Unit Test Dealer',
            'inn' => '7707083893',
        ];
        if ($personalDiscountPercent !== null) {
            $profileAttributes['personal_discount_percent'] = $personalDiscountPercent;
        }
        $profile = new DealerProfile($profileAttributes);
        $profile->save(false);

        return User::findOne((int)$user->id);
    }
}
