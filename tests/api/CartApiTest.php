<?php

namespace tests\api;

use app\models\CartItem;
use app\models\CatalogProduct;
use app\models\GuestSession;
use app\models\Order;
use app\models\User;
use app\models\UserProfile;
use Yii;
use yii\web\ForbiddenHttpException;
use yii\web\UnauthorizedHttpException;

class CartApiTest extends ApiTestCase
{
    private const SESSION_ID = 'guest-session-cart-01';
    private const GUEST_PHONE = '79991112233';

    protected function _before(): void
    {
        parent::_before();
        Yii::$app->user->setIdentity(null);
        Yii::$app->request->headers->remove('Authorization');
        Yii::$app->request->headers->remove('X-Session-ID');
        Yii::$app->request->setBodyParams([]);
        Yii::$app->request->setQueryParams([]);

        CartItem::deleteAll(['session_id' => self::SESSION_ID]);
        GuestSession::deleteAll(['session_id' => self::SESSION_ID]);
        Order::deleteAll(['session_id' => self::SESSION_ID]);
        CatalogProduct::deleteAll(['like', 'slug', 'cart-api-test-%', false]);
        User::deleteAll(['phone' => self::GUEST_PHONE]);
    }

    public function testGuestCartCrud(): void
    {
        $product = $this->createProduct();
        $this->withSession(self::SESSION_ID);

        $added = $this->postJson('api/v1/cart/add-item', [
            'productId' => $product->slug,
            'quantity' => 2,
        ]);
        verify($added['productId'])->equals($product->slug);
        verify($added['quantity'])->equals(2);

        $cart = $this->getJson('api/v1/cart/index');
        verify($cart['totalQuantity'])->equals(2);
        verify($cart['items'][0]['productId'])->equals($product->slug);

        $updated = $this->patchJson('api/v1/cart/update-item', ['quantity' => 3], ['productId' => $product->slug]);
        verify($updated['quantity'])->equals(3);

        $this->deleteJson('api/v1/cart/remove-item', ['productId' => $product->slug]);
        verify(Yii::$app->response->statusCode)->equals(204);
        verify($this->getJson('api/v1/cart/index')['totalQuantity'])->equals(0);
    }

    public function testGuestRequiresSession(): void
    {
        $product = $this->createProduct();
        $this->expectException(UnauthorizedHttpException::class);
        $this->postJson('api/v1/cart/add-item', [
            'productId' => $product->slug,
        ]);
    }

    public function testGuestCheckoutWithoutComments(): void
    {
        $product = $this->createProduct();
        $this->withSession(self::SESSION_ID);
        $this->postJson('api/v1/cart/add-item', [
            'productId' => $product->slug,
            'quantity' => 1,
        ]);

        $order = $this->postJson('api/v1/order/create', [
            'customerName' => 'Гость Тест',
            'customerPhone' => '+79991112233',
            'customerEmail' => 'guest@example.com',
            'deliveryAddress' => 'Москва',
        ]);

        verify(Yii::$app->response->statusCode)->equals(201);
        verify($order['number'])->notEmpty();
        verify($order['customerName'])->equals('Гость Тест');
        verify($order['items'][0]['comment'])->null();
        verify($this->getJson('api/v1/cart/index')['totalQuantity'])->equals(0);

        $viewed = $this->runAction('api/v1/order/view', ['number' => $order['number']]);
        verify($viewed['number'])->equals($order['number']);

        $customer = User::findOne(['phone' => self::GUEST_PHONE, 'type' => User::TYPE_CUSTOMER]);
        verify($customer)->notNull();
        $profile = UserProfile::findOne(['user_id' => (int)$customer->id]);
        verify($profile)->notNull();
        verify($profile->email)->equals('guest@example.com');
    }

    public function testGuestCheckoutRejectsComment(): void
    {
        $product = $this->createProduct();
        $this->withSession(self::SESSION_ID);
        $this->postJson('api/v1/cart/add-item', [
            'productId' => $product->slug,
            'quantity' => 1,
        ]);

        $this->expectException(ForbiddenHttpException::class);
        $this->postJson('api/v1/order/create', [
            'customerName' => 'Гость Тест',
            'customerPhone' => '+79991112233',
            'comment' => 'Нельзя',
        ]);
    }

    public function testGuestCannotSetItemComment(): void
    {
        $product = $this->createProduct();
        $this->withSession(self::SESSION_ID);
        $this->postJson('api/v1/cart/add-item', [
            'productId' => $product->slug,
            'quantity' => 1,
        ]);

        $this->expectException(UnauthorizedHttpException::class);
        $this->patchJson('api/v1/cart/update-item-comment', ['comment' => 'test'], ['productId' => $product->slug]);
    }

    private function createProduct(): CatalogProduct
    {
        $product = new CatalogProduct([
            'slug' => 'cart-api-test-' . substr(md5(uniqid('', true)), 0, 10),
            'title' => 'Товар для корзины',
            'href' => '/product/cart-test',
            'price_display' => '10 000 ₽',
            'is_active' => true,
            'sort_order' => 0,
        ]);
        verify($product->save(false))->true();

        return $product;
    }
}
