<?php

namespace tests\unit\services;

use app\models\CatalogProduct;
use app\models\CatalogPromotion;
use app\models\User;
use app\services\dealer\DealerPricingService;
use app\services\promotion\CatalogPromotionResolver;
use Codeception\Test\Unit;
use Yii;

class CatalogPromotionDealerPricingTest extends Unit
{
    private ?int $modelId = null;

    private ?CatalogProduct $product = null;

    protected function _before(): void
    {
        parent::_before();
        Yii::$app->db->open();

        $this->modelId = (int)Yii::$app->db->createCommand(
            'SELECT id FROM {{%catalog_models}} ORDER BY id LIMIT 1'
        )->queryScalar();
        if ($this->modelId <= 0) {
            $this->markTestSkipped('No catalog models in test database.');
        }

        $this->product = CatalogProduct::find()
            ->where(['model_id' => $this->modelId, 'is_active' => true])
            ->andWhere(['not', ['price_amount' => null]])
            ->orderBy(['id' => SORT_ASC])
            ->one();
        if ($this->product === null) {
            $this->markTestSkipped('No priced active product for test model.');
        }

        if (Yii::$app->db->schema->getTableSchema(CatalogPromotion::tableName(), true) === null) {
            $this->markTestSkipped('catalog_promotions is not migrated in test DB.');
        }

        CatalogPromotion::deleteAll(['like', 'title', 'unit-dealer-price-%', false]);
    }

    public function testPromotionPriceIgnoresDealerDiscount(): void
    {
        $retail = (int)$this->product->price_amount;
        $promotion = new CatalogPromotion([
            'title' => 'unit-dealer-price-' . substr(md5((string)microtime(true)), 0, 6),
            'discount_type' => CatalogPromotion::DISCOUNT_PERCENT,
            'discount_value' => 10,
            'starts_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'ends_at' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'scope_type' => CatalogPromotion::SCOPE_MODEL,
            'catalog_model_id' => $this->modelId,
        ]);
        verify($promotion->save(false))->true();

        $expected = (int)round($retail * 0.9);
        $resolver = new CatalogPromotionResolver();
        $service = new DealerPricingService();

        $dealerA = $this->createDealer(5.0);
        $dealerB = $this->createDealer(30.0);

        $pricesA = $service->buildProductPrices($this->product, $dealerA, $resolver);
        $pricesB = $service->buildProductPrices($this->product, $dealerB, $resolver);

        $this->assertSame($expected, $pricesA['dealerPrice']);
        $this->assertSame($expected, $pricesB['dealerPrice']);
        $this->assertArrayNotHasKey('dealerDiscountPercent', $pricesA);
        $this->assertArrayHasKey('catalogPromotion', $pricesA);

        $card = $this->product->toListingCard($dealerA);
        $this->assertSame('Акция', $card['badge']['text'] ?? null);
        $this->assertSame('promotion', $card['badge']['variant'] ?? null);
        $this->assertArrayNotHasKey('badges', $card);
    }

    public function testExpiredPromotionDoesNotAddPromotionBadge(): void
    {
        $promotion = new CatalogPromotion([
            'title' => 'unit-dealer-price-expired-' . substr(md5((string)microtime(true)), 0, 6),
            'discount_type' => CatalogPromotion::DISCOUNT_PERCENT,
            'discount_value' => 10,
            'starts_at' => date('Y-m-d H:i:s', strtotime('-10 days')),
            'ends_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'scope_type' => CatalogPromotion::SCOPE_MODEL,
            'catalog_model_id' => $this->modelId,
        ]);
        verify($promotion->save(false))->true();

        $dealer = $this->createDealer(10.0);
        $card = $this->product->toListingCard($dealer);
        $this->assertNotSame('promotion', $card['badge']['variant'] ?? null);
    }

    private function createDealer(float $discount): User
    {
        $user = new User(['type' => User::TYPE_DEALER]);
        $profile = new \stdClass();
        $profile->personal_discount_percent = $discount;
        $user->populateRelation('dealerProfile', $profile);

        return $user;
    }
}
