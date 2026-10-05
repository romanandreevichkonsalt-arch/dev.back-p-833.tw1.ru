<?php

namespace tests\unit\services;

use app\models\CatalogPromotion;
use app\models\PromotionBanner;
use app\models\PromotionPopup;
use app\modules\admin\models\CatalogPromotionForm;
use app\modules\admin\models\PromotionBannerForm;
use app\modules\admin\models\PromotionPopupForm;
use app\services\promotion\PromotionBannerAdminService;
use app\services\promotion\PromotionDealerApiService;
use app\services\promotion\PromotionPopupAdminService;
use Codeception\Test\Unit;
use Yii;

class PromotionMarketingSaveTest extends Unit
{
    private ?int $modelId = null;

    protected function _before(): void
    {
        parent::_before();
        Yii::$app->db->open();

        $this->modelId = (int)Yii::$app->db->createCommand(
            'SELECT id FROM {{%catalog_models}} ORDER BY id LIMIT 1'
        )->queryScalar();
        if ($this->modelId <= 0) {
            $this->modelId = null;
        }

        if (Yii::$app->db->schema->getTableSchema(PromotionBanner::tableName(), true) !== null) {
            PromotionBanner::deleteAll(['like', 'headline', 'unit-banner-%', false]);
        }

        if (Yii::$app->db->schema->getTableSchema(CatalogPromotion::tableName(), true) !== null) {
            CatalogPromotion::deleteAll(['like', 'title', 'unit-sale-%', false]);
        }
    }

    public function testPromotionBannerFormValidatesWithoutDatabase(): void
    {
        $form = new PromotionBannerForm([
            'headline' => 'Valid headline',
            'valid_from' => date('Y-m-d'),
            'valid_to' => date('Y-m-d', strtotime('+1 day')),
        ]);

        verify($form->validate())->true();
    }

    public function testPromotionBannerFormLoadWithEmptyTemplateIdAndNewPromo(): void
    {
        $form = new PromotionBannerForm();
        $loaded = $form->load([
            'PromotionBannerForm' => [
                'headline' => 'Banner',
                'valid_from' => date('Y-m-d'),
                'valid_to' => date('Y-m-d', strtotime('+1 day')),
                'promo_mode' => PromotionBannerForm::PROMO_NONE,
                'template_id' => '',
                'promo_code' => 'NEWCODE',
                'promo_title' => 'New promo',
                'promo_discount_percent' => '12',
            ],
        ]);

        verify($loaded)->true();
        verify($form->promo_mode)->equals(PromotionBannerForm::PROMO_CREATE);
        verify($form->template_id)->null();
        verify($form->validate())->true();
    }

    public function testSavePromotionBanner(): void
    {
        if (Yii::$app->db->schema->getTableSchema(PromotionBanner::tableName(), true) === null) {
            $this->markTestSkipped('promotion_banners is not migrated in test DB.');
        }

        $form = new PromotionBannerForm([
            'headline' => 'unit-banner-' . substr(md5((string)microtime(true)), 0, 8),
            'body_text' => 'Body',
            'valid_from' => date('Y-m-d'),
            'valid_to' => date('Y-m-d', strtotime('+7 days')),
        ]);
        verify($form->validate())->true();

        $banner = (new PromotionBannerAdminService())->save($form);

        $this->assertGreaterThan(0, (int)$banner->id);
        $this->assertSame($form->headline, $banner->headline);
    }

    public function testListVisibleBannersReturnsAllOnAirRecords(): void
    {
        if (Yii::$app->db->schema->getTableSchema(PromotionBanner::tableName(), true) === null) {
            $this->markTestSkipped('promotion_banners is not migrated in test DB.');
        }

        $suffix = substr(md5((string)microtime(true)), 0, 8);
        $today = date('Y-m-d');
        $service = new PromotionBannerAdminService();
        $visibleA = $service->save(new PromotionBannerForm([
            'headline' => 'unit-banner-a-' . $suffix,
            'valid_from' => $today,
            'valid_to' => date('Y-m-d', strtotime('+7 days')),
        ]));
        $visibleB = $service->save(new PromotionBannerForm([
            'headline' => 'unit-banner-b-' . $suffix,
            'valid_from' => $today,
            'valid_to' => date('Y-m-d', strtotime('+7 days')),
        ]));
        $service->save(new PromotionBannerForm([
            'headline' => 'unit-banner-expired-' . $suffix,
            'valid_from' => date('Y-m-d', strtotime('-14 days')),
            'valid_to' => date('Y-m-d', strtotime('-7 days')),
        ]));

        $items = (new PromotionDealerApiService())->listVisibleBanners();
        $ids = array_column($items, 'id');

        $this->assertContains((int)$visibleA->id, $ids);
        $this->assertContains((int)$visibleB->id, $ids);
        $this->assertGreaterThanOrEqual(2, count(array_intersect($ids, [(int)$visibleA->id, (int)$visibleB->id])));
    }

    public function testSavePromotionPopup(): void
    {
        if (Yii::$app->db->schema->getTableSchema(PromotionPopup::tableName(), true) === null) {
            $this->markTestSkipped('promotion_popup is not migrated in test DB.');
        }

        $form = new PromotionPopupForm([
            'headline' => 'unit-popup-' . substr(md5((string)microtime(true)), 0, 8),
            'body_text' => 'Popup text',
        ]);
        verify($form->validate())->true();

        $popup = (new PromotionPopupAdminService())->save($form);

        $this->assertGreaterThan(0, (int)$popup->id);
        $this->assertSame($form->headline, $popup->headline);
    }

    public function testSaveCatalogPromotionViaForm(): void
    {
        if (Yii::$app->db->schema->getTableSchema(CatalogPromotion::tableName(), true) === null) {
            $this->markTestSkipped('catalog_promotions is not migrated in test DB.');
        }

        $form = new CatalogPromotionForm([
            'title' => 'unit-sale-' . substr(md5((string)microtime(true)), 0, 8),
            'discount_type' => CatalogPromotion::DISCOUNT_PERCENT,
            'discount_value' => 15,
            'starts_at' => date('Y-m-d\T00:00'),
            'ends_at' => date('Y-m-d\T23:59', strtotime('+1 month')),
            'scope_type' => CatalogPromotion::SCOPE_MODEL,
            'catalog_model_id' => $this->modelId,
        ]);
        verify($form->validate())->true();

        $promotion = new CatalogPromotion();
        $form->applyTo($promotion);
        verify($promotion->save())->true();

        $this->assertGreaterThan(0, (int)$promotion->id);
        $this->assertSame($this->modelId, (int)$promotion->catalog_model_id);
        $this->assertSame(15.0, (float)$promotion->discount_value);
    }
}
