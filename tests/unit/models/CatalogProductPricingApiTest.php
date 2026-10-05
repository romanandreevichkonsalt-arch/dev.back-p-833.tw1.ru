<?php

namespace tests\unit\models;

use app\models\CatalogProduct;
use app\models\User;
use Codeception\Test\Unit;

class CatalogProductPricingApiTest extends Unit
{
    public function testGuestGetsRetailOnly(): void
    {
        $product = new CatalogProduct([
            'price_amount' => 100000,
            'price_display' => '100 000 ₽',
        ]);

        $payload = $product->buildPricingApiPayload(null);

        $this->assertSame(100000, $payload['retailPrice']);
        $this->assertArrayNotHasKey('dealerPrice', $payload);
    }

    public function testDealerGetsRetailAndDealerPrice(): void
    {
        $product = new CatalogProduct([
            'price_amount' => 100000,
            'price_display' => '100 000 ₽',
        ]);
        $dealer = new User([
            'type' => User::TYPE_DEALER,
        ]);

        $payload = $product->buildPricingApiPayload($dealer);

        $this->assertSame(100000, $payload['retailPrice']);
        $this->assertArrayHasKey('dealerPrice', $payload);
        $this->assertArrayNotHasKey('catalogRetailPrice', $payload);
    }

    public function testMenuApiItemExposesRetailPrice(): void
    {
        $product = new CatalogProduct([
            'slug' => 'test-menu-item',
            'title' => 'Test',
            'href' => '/product/test-menu-item',
            'price_amount' => 100000,
            'price_display' => '100 000 ₽',
        ]);

        $item = $product->toMenuApiItem(null);

        $this->assertSame(100000, $item['retailPrice']);
        $this->assertSame('100 000 ₽', $item['priceDisplay']);
        $this->assertSame('100 000 ₽', $item['price']);
    }

    public function testListingCardExposesDealerPrice(): void
    {
        $product = new CatalogProduct([
            'slug' => 'test-pricing-card',
            'title' => 'Test',
            'price_amount' => 50000,
            'price_display' => '50 000 ₽',
        ]);
        $dealer = new User([
            'type' => User::TYPE_DEALER,
        ]);

        $card = $product->toListingCard($dealer);

        $this->assertSame(50000, $card['retailPrice']);
        $this->assertArrayHasKey('dealerPrice', $card);
        $this->assertArrayNotHasKey('priceDisplay', $card);
        $this->assertArrayNotHasKey('badges', $card);
    }

    public function testSearchIndexDocumentOmitsListingOnlyFields(): void
    {
        $product = new CatalogProduct([
            'slug' => 'index-sku',
            'title' => 'Index SKU',
            'price_amount' => 1000,
            'price_display' => '1 000 ₽',
        ]);

        $document = $product->toSearchIndexDocument();

        $this->assertSame('Index SKU', $document['title']);
        $this->assertArrayHasKey('_productId', $document);
        $this->assertArrayNotHasKey('swatches', $document);
        $this->assertArrayNotHasKey('swatchCount', $document);
        $this->assertArrayNotHasKey('fabric', $document);
        $this->assertArrayHasKey('priceDisplay', $document);
    }
}
