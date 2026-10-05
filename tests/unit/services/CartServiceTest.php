<?php

namespace tests\unit\services;

use app\models\CartItem;
use app\models\CatalogProduct;
use app\models\GuestSession;
use app\models\User;
use app\services\cart\CartService;
use app\services\guest\ApiOwnerContext;
use Codeception\Test\Unit;
use Yii;

class CartServiceTest extends Unit
{
    private const SESSION_ID = 'unit-cart-sync-session';

    protected function _before(): void
    {
        parent::_before();
        Yii::$app->db->open();

        CartItem::deleteAll(['session_id' => self::SESSION_ID]);
        GuestSession::deleteAll(['session_id' => self::SESSION_ID]);
        CatalogProduct::deleteAll(['like', 'slug', 'unit-cart-%', false]);
        User::deleteAll(['username' => 'unit-cart-user']);
    }

    public function testGuestOwnerAddAndSyncMergesQuantity(): void
    {
        $product = $this->createProduct();
        $service = new CartService();

        $service->addItem(new ApiOwnerContext(null, self::SESSION_ID), $product->slug, 2);

        $user = new User([
            'username' => 'unit-cart-user',
            'type' => User::TYPE_CUSTOMER,
            'phone' => '79990001122',
            'password_hash' => 'x',
        ]);
        verify($user->save(false))->true();

        $service->addItem(new ApiOwnerContext((int)$user->id, null), $product->slug, 1);
        $result = $service->sync($user, self::SESSION_ID);

        verify($result['mergedCount'])->equals(1);
        verify($result['resultTotal'])->equals(3);
        verify(CartItem::find()->where(['session_id' => self::SESSION_ID])->count())->equals(0);
        verify(CartItem::find()->where(['user_id' => (int)$user->id])->count())->equals(1);
    }

    private function createProduct(): CatalogProduct
    {
        $product = new CatalogProduct([
            'slug' => 'unit-cart-' . substr(md5(uniqid('', true)), 0, 8),
            'title' => 'Unit cart product',
            'href' => '/product/unit-cart',
            'price_display' => '5 000 ₽',
            'is_active' => true,
            'sort_order' => 0,
        ]);
        verify($product->save(false))->true();

        return $product;
    }
}
