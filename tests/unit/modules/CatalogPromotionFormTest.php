<?php

namespace tests\unit\modules;

use app\models\CatalogProduct;
use app\models\CatalogPromotion;
use app\modules\admin\models\CatalogPromotionForm;
use Codeception\Test\Unit;
use Yii;

class CatalogPromotionFormTest extends Unit
{
    public function testFixedPriceMustBeBelowRetailForProductScope(): void
    {
        $product = CatalogProduct::find()
            ->where(['not', ['price_amount' => null]])
            ->andWhere(['>', 'price_amount', 0])
            ->orderBy(['id' => SORT_ASC])
            ->one();
        if ($product === null) {
            $this->markTestSkipped('No priced product in test database.');
        }

        $retail = (int)$product->price_amount;
        $form = new CatalogPromotionForm([
            'title' => 'unit-form-fixed',
            'discount_type' => CatalogPromotion::DISCOUNT_FIXED,
            'discount_value' => (float)$retail,
            'starts_at' => date('Y-m-d\TH:i', strtotime('+1 hour')),
            'ends_at' => date('Y-m-d\TH:i', strtotime('+2 days')),
            'scope_type' => CatalogPromotion::SCOPE_PRODUCT,
            'catalog_model_id' => (int)$product->model_id,
            'catalog_product_id' => (int)$product->id,
        ]);

        $this->assertFalse($form->validate(['discount_value']));
        $this->assertNotEmpty($form->getErrors('discount_value'));
    }

    public function testFixedAmountAbove100IsAllowedForModelScope(): void
    {
        $form = new CatalogPromotionForm([
            'title' => 'unit-form-fixed-sum',
            'discount_type' => CatalogPromotion::DISCOUNT_FIXED,
            'discount_value' => 20000.0,
            'starts_at' => date('Y-m-d\TH:i', strtotime('+1 hour')),
            'ends_at' => date('Y-m-d\TH:i', strtotime('+2 days')),
            'scope_type' => CatalogPromotion::SCOPE_MODEL,
            'catalog_model_id' => 1,
        ]);

        $this->assertTrue($form->validate(['discount_type', 'discount_value']));
        $this->assertEmpty($form->getErrors('discount_value'));
    }

    public function testLoadAcceptsEmptyStringProductId(): void
    {
        $form = new CatalogPromotionForm();
        $loaded = $form->load([
            'title' => 't',
            'discount_type' => CatalogPromotion::DISCOUNT_PERCENT,
            'discount_value' => '10',
            'starts_at' => date('Y-m-d\TH:i', strtotime('+1 hour')),
            'ends_at' => date('Y-m-d\TH:i', strtotime('+2 days')),
            'scope_type' => CatalogPromotion::SCOPE_MODEL,
            'catalog_model_id' => '5',
            'catalog_product_id' => '',
            'image_media_id' => '',
        ], '');

        $this->assertTrue($loaded);
        $this->assertSame(5, $form->catalog_model_id);
        $this->assertNull($form->catalog_product_id);
        $this->assertNull($form->image_media_id);
    }
}
