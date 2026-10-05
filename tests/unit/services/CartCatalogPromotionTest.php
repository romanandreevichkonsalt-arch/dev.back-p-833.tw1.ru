<?php

namespace tests\unit\services;

use app\models\CatalogProduct;
use app\models\CatalogPromotion;
use app\models\User;
use app\services\cart\CartService;
use Codeception\Test\Unit;
use Yii;

class CartCatalogPromotionTest extends Unit
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
            ->orderBy(['id' => SORT_ASC])
            ->one();
        if ($this->product === null) {
            $this->markTestSkipped('No active product for test model.');
        }

        if (Yii::$app->db->schema->getTableSchema(CatalogPromotion::tableName(), true) === null) {
            $this->markTestSkipped('catalog_promotions is not migrated in test DB.');
        }

        CatalogPromotion::deleteAll(['like', 'title', 'unit-cart-line-%', false]);
    }

    public function testDealerCartLineIncludesCatalogPromotionDiscount(): void
    {
        $promotion = new CatalogPromotion([
            'title' => 'unit-cart-line-' . substr(md5((string)microtime(true)), 0, 6),
            'discount_type' => CatalogPromotion::DISCOUNT_PERCENT,
            'discount_value' => 10,
            'starts_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'ends_at' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'scope_type' => CatalogPromotion::SCOPE_MODEL,
            'catalog_model_id' => $this->modelId,
        ]);
        verify($promotion->save(false))->true();

        $dealerHigh = new User([
            'username' => 'unit-cart-promo-dealer-a-' . substr(md5((string)microtime(true)), 0, 6),
            'type' => User::TYPE_DEALER,
            'phone' => '79991112233',
            'password_hash' => 'x',
        ]);
        verify($dealerHigh->save(false))->true();
        $dealerLow = new User([
            'username' => 'unit-cart-promo-dealer-b-' . substr(md5((string)microtime(true)), 0, 6),
            'type' => User::TYPE_DEALER,
            'phone' => '79991112244',
            'password_hash' => 'x',
        ]);
        verify($dealerLow->save(false))->true();

        $lineHigh = (new CartService())->buildCartLineForProduct($this->product, 1, $dealerHigh);
        $lineLow = (new CartService())->buildCartLineForProduct($this->product, 1, $dealerLow);

        $this->assertTrue($lineHigh['hasCatalogPromotion'] ?? false);
        $this->assertSame($lineHigh['lineTotal'], $lineLow['lineTotal']);
        $this->assertSame($lineHigh['unitPrice'], $lineLow['unitPrice']);
        $this->assertGreaterThan(0.0, (float)($lineHigh['catalogPromotionDiscount'] ?? 0));

        $dealerHigh->delete();
        $dealerLow->delete();
    }
}
