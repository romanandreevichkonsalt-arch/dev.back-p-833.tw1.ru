<?php

namespace tests\unit\services;

use app\models\DealerCartCheckout;
use app\models\DealerCashbackAccount;
use app\models\DealerPromoGrant;
use app\models\User;
use app\services\cart\CartCheckoutService;
use app\services\guest\ApiOwnerContext;
use Codeception\Test\Unit;
use Yii;

class CartCheckoutServiceTest extends Unit
{
    private const USERNAME = 'unit-cart-checkout-dealer';

    private ?int $catalogModelId = null;

    protected function _before(): void
    {
        parent::_before();
        Yii::$app->db->open();

        $this->catalogModelId = (int)Yii::$app->db->createCommand(
            'SELECT id FROM {{%catalog_models}} ORDER BY id LIMIT 1'
        )->queryScalar();
        if ($this->catalogModelId <= 0) {
            $this->markTestSkipped('No catalog models in test database.');
        }

        $user = User::findOne(['username' => self::USERNAME]);
        if ($user !== null) {
            DealerCartCheckout::deleteAll(['user_id' => (int)$user->id]);
            DealerPromoGrant::deleteAll(['user_id' => (int)$user->id]);
            DealerCashbackAccount::deleteAll(['user_id' => (int)$user->id]);
            $user->delete();
        }

        $customer = User::findOne(['username' => self::USERNAME . '-customer']);
        if ($customer !== null) {
            DealerCartCheckout::deleteAll(['user_id' => (int)$customer->id]);
            DealerPromoGrant::deleteAll(['user_id' => (int)$customer->id]);
            $customer->delete();
        }

    }

    public function testPromoCodeDoesNotApplyToLinesWithCatalogPromotion(): void
    {
        $user = $this->createDealer();
        $grant = $this->createGrant((int)$user->id, 'NOVINKA_TEST', 10, $this->catalogModelId);
        $this->attachPromoToCheckout((int)$user->id, (int)$grant->id);

        $payload = (new CartCheckoutService())->enrichPayload((int)$user->id, [
            [
                'lineTotal' => 90_000,
                'catalogModelId' => $this->catalogModelId,
                'hasCatalogPromotion' => true,
                'catalogPromotionDiscount' => 10_000,
                'retailLineTotal' => 120_000,
            ],
            ['lineTotal' => 50_000, 'catalogModelId' => $this->catalogModelId],
        ], 2, $user);

        $this->assertSame(140_000.0, $payload['subtotal']);
        $this->assertSame(10_000.0, $payload['catalogPromotionDiscount']);
        $this->assertSame(5_000.0, $payload['promoDiscount']);
        $this->assertSame(10_000.0, $payload['discounts']['promotion']['amount']);
        $this->assertSame(135_000.0, $payload['total']);
    }

    public function testPromoDiscountAppliesOnlyToScopedModelItems(): void
    {
        $user = $this->createDealer();
        $grant = $this->createGrant((int)$user->id, 'NOVINKA_TEST', 10, $this->catalogModelId);
        $this->attachPromoToCheckout((int)$user->id, (int)$grant->id);

        $payload = (new CartCheckoutService())->enrichPayload((int)$user->id, [
            ['lineTotal' => 100_000, 'catalogModelId' => $this->catalogModelId],
            ['lineTotal' => 50_000, 'catalogModelId' => $this->catalogModelId + 9999],
        ], 2, $user);

        $this->assertSame(150_000.0, $payload['subtotal']);
        $this->assertSame(10_000.0, $payload['promoDiscount']);
        $this->assertSame(140_000.0, $payload['total']);
    }

    public function testPromoWithoutModelScopeAppliesToEntireCart(): void
    {
        $user = $this->createDealer();
        $grant = $this->createGrant((int)$user->id, 'VYSTAVKA', 10, null);
        $this->attachPromoToCheckout((int)$user->id, (int)$grant->id);

        $payload = (new CartCheckoutService())->enrichPayload((int)$user->id, [
            ['lineTotal' => 100_000, 'catalogModelId' => $this->catalogModelId],
            ['lineTotal' => 50_000, 'catalogModelId' => $this->catalogModelId + 9999],
        ], 2, $user);

        $this->assertSame(150_000.0, $payload['subtotal']);
        $this->assertSame(15_000.0, $payload['promoDiscount']);
    }

    public function testCheckoutTotalsCapCashbackByBalance(): void
    {
        $user = $this->createDealer();
        $this->createCashbackAccount((int)$user->id, 3_000.0);
        $this->attachCashbackToCheckout((int)$user->id, 10_000.0);

        Yii::$app->user->setIdentity($user);
        $owner = new ApiOwnerContext((int)$user->id, null);
        $totals = (new CartCheckoutService())->getCheckoutTotalsForLines($owner, [
            ['lineTotal' => 20_000, 'catalogModelId' => $this->catalogModelId],
        ]);

        $this->assertSame(20_000.0, $totals['subtotal']);
        $this->assertSame(3_000.0, $totals['cashbackUsed']);
        $this->assertSame(17_000.0, $totals['total']);
    }

    public function testCashbackDoesNotApplyToCatalogPromotionLines(): void
    {
        $user = $this->createDealer();
        $this->createCashbackAccount((int)$user->id, 150_000.0);
        $this->attachCashbackToCheckout((int)$user->id, 150_000.0);

        Yii::$app->user->setIdentity($user);
        $owner = new ApiOwnerContext((int)$user->id, null);
        $totals = (new CartCheckoutService())->getCheckoutTotalsForLines($owner, [
            ['lineTotal' => 200_000, 'catalogModelId' => $this->catalogModelId, 'hasCatalogPromotion' => true],
        ]);

        $this->assertSame(200_000.0, $totals['subtotal']);
        $this->assertSame(0.0, $totals['cashbackUsed']);
        $this->assertSame(200_000.0, $totals['total']);
    }

    public function testCheckoutTotalsCapCashbackByFiftyPercentOfSubtotal(): void
    {
        $user = $this->createDealer();
        $this->createCashbackAccount((int)$user->id, 150_000.0);
        $this->attachCashbackToCheckout((int)$user->id, 150_000.0);

        Yii::$app->user->setIdentity($user);
        $owner = new ApiOwnerContext((int)$user->id, null);
        $totals = (new CartCheckoutService())->getCheckoutTotalsForLines($owner, [
            ['lineTotal' => 200_000, 'catalogModelId' => $this->catalogModelId],
        ]);

        $this->assertSame(200_000.0, $totals['subtotal']);
        $this->assertSame(100_000.0, $totals['cashbackUsed']);
        $this->assertSame(100_000.0, $totals['total']);
    }

    public function testNonDealerDoesNotReceivePromoDiscount(): void
    {
        $user = new User([
            'username' => self::USERNAME . '-customer',
            'type' => User::TYPE_CUSTOMER,
            'phone' => '79990005566',
            'password_hash' => 'x',
        ]);
        verify($user->save(false))->true();

        $grant = $this->createGrant((int)$user->id, 'NOVINKA_TEST', 10, $this->catalogModelId);
        $this->attachPromoToCheckout((int)$user->id, (int)$grant->id);

        $payload = (new CartCheckoutService())->enrichPayload((int)$user->id, [
            ['lineTotal' => 100_000, 'catalogModelId' => $this->catalogModelId],
        ], 1, $user);

        $this->assertSame(0.0, $payload['promoDiscount']);
        $this->assertNull($payload['promo']);

        $checkout = DealerCartCheckout::findOne((int)$user->id);
        $this->assertNotNull($checkout);
        $this->assertNull($checkout->promo_grant_id);
    }

    private function createDealer(): User
    {
        $user = new User([
            'username' => self::USERNAME,
            'type' => User::TYPE_DEALER,
            'phone' => '79990003344',
            'password_hash' => 'x',
        ]);
        verify($user->save(false))->true();

        return $user;
    }

    private function createGrant(int $userId, string $code, float $discountPercent, ?int $catalogModelId): DealerPromoGrant
    {
        $grant = new DealerPromoGrant([
            'user_id' => $userId,
            'code' => $code,
            'discount_percent' => $discountPercent,
            'catalog_model_id' => $catalogModelId,
            'source' => DealerPromoGrant::SOURCE_NOVELTY,
            'is_active' => true,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        verify($grant->save(false))->true();

        return $grant;
    }

    private function attachPromoToCheckout(int $userId, int $grantId): void
    {
        $checkout = new DealerCartCheckout([
            'user_id' => $userId,
            'promo_grant_id' => $grantId,
            'cashback_amount' => 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        verify($checkout->save(false))->true();
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
        verify($account->save(false))->true();
    }

    private function attachCashbackToCheckout(int $userId, float $amount): void
    {
        $checkout = new DealerCartCheckout([
            'user_id' => $userId,
            'promo_grant_id' => null,
            'cashback_amount' => $amount,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        verify($checkout->save(false))->true();
    }
}
