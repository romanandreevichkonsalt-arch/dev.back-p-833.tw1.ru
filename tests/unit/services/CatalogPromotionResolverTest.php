<?php

namespace tests\unit\services;

use app\models\CatalogPromotion;
use app\services\promotion\CatalogPromotionResolver;
use Codeception\Test\Unit;
use Yii;

class CatalogPromotionResolverTest extends Unit
{
    private ?int $modelId = null;

    private ?int $productId = null;

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

        $this->productId = (int)Yii::$app->db->createCommand(
            'SELECT id FROM {{%catalog_products}} WHERE model_id = :mid ORDER BY id LIMIT 1',
            [':mid' => $this->modelId]
        )->queryScalar();
        if ($this->productId <= 0) {
            $this->markTestSkipped('No catalog products for test model.');
        }

        if (Yii::$app->db->schema->getTableSchema(CatalogPromotion::tableName(), true) === null) {
            $this->markTestSkipped('catalog_promotions is not migrated in test DB.');
        }

        CatalogPromotion::deleteAll(['like', 'title', 'unit-promo-%', false]);
    }

    public function testProductScopeOverridesModelScope(): void
    {
        $now = date('Y-m-d H:i:s');
        $modelPromo = $this->createPromotion('unit-promo-model', CatalogPromotion::SCOPE_MODEL, 10, null);
        $productPromo = $this->createPromotion('unit-promo-product', CatalogPromotion::SCOPE_PRODUCT, 20, $this->productId);

        $resolver = new CatalogPromotionResolver();
        $found = $resolver->findForProduct($this->productId, $this->modelId, $now);

        $this->assertNotNull($found);
        $this->assertSame((int)$productPromo->id, (int)$found->id);
        $this->assertNotSame((int)$modelPromo->id, (int)$found->id);
    }

    public function testPercentDiscountOnDealerLineTotal(): void
    {
        $promotion = new CatalogPromotion([
            'title' => 'unit-promo-calc',
            'discount_type' => CatalogPromotion::DISCOUNT_PERCENT,
            'discount_value' => 10,
            'starts_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'ends_at' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'scope_type' => CatalogPromotion::SCOPE_MODEL,
            'catalog_model_id' => $this->modelId,
        ]);
        verify($promotion->save(false))->true();

        $resolver = new CatalogPromotionResolver();
        $amount = $resolver->calculateDiscountAmount($promotion, 100_000, 2);

        $this->assertSame(20_000.0, $amount);
    }

    private function createPromotion(string $title, string $scope, float $value, ?int $productId): CatalogPromotion
    {
        $promotion = new CatalogPromotion([
            'title' => $title,
            'discount_type' => CatalogPromotion::DISCOUNT_PERCENT,
            'discount_value' => $value,
            'starts_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'ends_at' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'scope_type' => $scope,
            'catalog_model_id' => $this->modelId,
            'catalog_product_id' => $productId,
        ]);
        verify($promotion->save(false))->true();

        return $promotion;
    }
}
