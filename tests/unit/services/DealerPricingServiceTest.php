<?php

namespace tests\unit\services;

use app\models\CatalogProduct;
use app\models\User;
use app\services\dealer\DealerPricingService;
use Codeception\Test\Unit;

class DealerPricingServiceTest extends Unit
{
    public function testDefaultDiscountIsZero(): void
    {
        $user = $this->createDealer(null);
        $product = new CatalogProduct([
            'price_amount' => 100000,
            'price_display' => '100 000 ₽',
        ]);

        $service = new DealerPricingService();
        $prices = $service->buildProductPrices($product, $user);

        $this->assertSame(100000, $prices['retailPrice']);
        $this->assertSame(100000, $prices['dealerPrice']);
        $this->assertSame(0, $prices['dealerDiscountPercent']);
    }

    public function testExplicitZeroDiscountReturnsDealerPriceEqualRetail(): void
    {
        $user = $this->createDealer(0.0);
        $product = new CatalogProduct(['price_amount' => 250000]);

        $prices = (new DealerPricingService())->buildProductPrices($product, $user);

        $this->assertSame(250000, $prices['retailPrice']);
        $this->assertSame(250000, $prices['dealerPrice']);
        $this->assertSame(0, $prices['dealerDiscountPercent']);
    }

    public function testPersonalDiscountOverridesDefault(): void
    {
        $user = $this->createDealer(20.0);
        $product = new CatalogProduct(['price_amount' => 100000]);

        $prices = (new DealerPricingService())->buildProductPrices($product, $user);

        $this->assertSame(80000, $prices['dealerPrice']);
        $this->assertSame(20, $prices['dealerDiscountPercent']);
    }

    public function testGuestGetsOnlyRetailPrice(): void
    {
        $product = new CatalogProduct(['price_amount' => 50000]);
        $prices = (new DealerPricingService())->buildProductPrices($product, null);

        $this->assertSame(50000, $prices['retailPrice']);
        $this->assertArrayNotHasKey('dealerPrice', $prices);
    }

    public function testRetailPriceFallsBackToModelPriceCategoryForGeneratedSku(): void
    {
        $fabricCollection = new \app\models\CatalogFabricCollection([
            'price_category_id' => 10,
        ]);

        $color = new \app\models\CatalogFabricColor();
        $color->populateRelation('fabricCollection', $fabricCollection);

        $modelPrice = new \app\models\CatalogModelPrice([
            'price_category_id' => 10,
            'price_display' => '260 306 ₽',
        ]);
        $model = new \app\models\CatalogModel(['id' => 1]);
        $model->populateRelation('modelPrices', [$modelPrice]);

        $product = new CatalogProduct([
            'model_id' => 99,
            'price_amount' => null,
            'price_display' => null,
        ]);
        $product->populateRelation('catalogModel', $model);
        $product->populateRelation('fabricColor', $color);

        $service = new DealerPricingService();
        $this->assertSame(260306, $service->resolveRetailPrice($product));

        $dealer = $this->createDealer(10.0);
        $prices = $service->buildProductPrices($product, $dealer);
        $this->assertSame(260306, $prices['retailPrice']);
        $this->assertSame(234275, $prices['dealerPrice']);
    }

    private function createDealer(?float $discount): User
    {
        $user = new User([
            'type' => User::TYPE_DEALER,
        ]);
        $profile = new \stdClass();
        $profile->personal_discount_percent = $discount;
        $user->populateRelation('dealerProfile', $profile);

        return $user;
    }
}
