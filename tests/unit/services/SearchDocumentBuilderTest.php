<?php

namespace tests\unit\services;

use app\services\search\SearchDocumentBuilder;
use Codeception\Test\Unit;

class SearchDocumentBuilderTest extends Unit
{
    private SearchDocumentBuilder $builder;

    protected function _before(): void
    {
        $this->builder = new SearchDocumentBuilder();
    }

    public function testEnrichProductAddsNormalizedRankerFields(): void
    {
        $item = $this->builder->enrichProduct([
            'title' => 'Диван Турин',
            'type' => 'Диваны',
        ]);

        verify($item)->arrayHasKey('_searchText');
        verify($item)->arrayHasKey('_searchHaystackNormalized');
        verify($item)->arrayHasKey('_titleNormalized');
        verify($item['_titleNormalized'])->equals('диван турин');
        $this->assertArrayNotHasKey('description', $item);
        $this->assertArrayNotHasKey('_searchText', $item);
    }

    public function testCompactIndexDocumentStripsHeavyImageFields(): void
    {
        $compact = $this->builder->compactIndexDocument([
            'title' => 'SKU',
            'description' => 'Long text',
            '_searchText' => 'long search blob',
            'image' => ['src' => '/a.webp', 'alt' => 'A', 'srcSet' => ['medium' => '/m.webp']],
            'images' => [['src' => '/b.webp', 'alt' => 'B', 'width' => 100]],
        ]);

        $this->assertArrayNotHasKey('description', $compact);
        $this->assertArrayNotHasKey('_searchText', $compact);
        verify($compact['image'])->equals(['src' => '/a.webp', 'alt' => 'A']);
        verify($compact['images'][0])->equals(['src' => '/b.webp', 'alt' => 'B']);
    }

    public function testBuildsSearchTextFromProductFields(): void
    {
        $text = $this->builder->buildSearchText([
            'title' => 'Диван Турин',
            'subtitle' => 'Прямой',
            'type' => 'Диваны',
            'collection' => 'Артемида',
            'fabricColorLabel' => 'Бежевый',
            'materials' => ['Дуб', 'Ткань'],
            'model' => [
                'title' => 'Турин',
                'description' => 'Комфортная модель',
            ],
        ]);

        verify($text)->stringContainsString('Турин');
        verify($text)->stringContainsString('Артемида');
        verify($text)->stringContainsString('Бежевый');
        verify($text)->stringContainsString('Дуб');
    }

    public function testPublicProductContainsOnlySearchCardFields(): void
    {
        $item = $this->builder->toPublicProduct([
            'id' => 'turin',
            'slug' => 'turin',
            'title' => 'Диван Турин',
            'subcategoryLabel' => 'Прямые',
            'collection' => 'Артемида',
            'retailPrice' => 100000,
            'priceDisplay' => '100 000 ₽',
            'dealerPrice' => 90000,
            'dealerDiscountPercent' => 10,
            'badge' => ['text' => 'Новинка', 'variant' => 'new'],
            'href' => '/catalog/turin',
            'image' => ['src' => '/p.webp', 'alt' => 'SKU'],
            'images' => [['src' => '/model.webp', 'alt' => 'Model']],
            'swatches' => [['hex' => '#fff']],
            '_searchText' => 'internal',
        ]);

        verify($item)->equals([
            'id' => 'turin',
            'slug' => 'turin',
            'title' => 'Диван Турин',
            'subcategory' => 'Прямые',
            'collection' => 'Артемида',
            'image' => ['src' => '/p.webp', 'alt' => 'SKU'],
            'retailPrice' => 100000,
            'priceDisplay' => '100 000 ₽',
            'dealerPrice' => 90000,
            'dealerDiscountPercent' => 10,
            'badge' => ['text' => 'Новинка', 'variant' => 'new'],
            'href' => '/catalog/turin',
        ]);
    }

    public function testSearchImageUsesFirstModelPhotoWhenSkuPhotoMissing(): void
    {
        $item = $this->builder->toPublicProduct([
            'id' => 'turin',
            'title' => 'Диван Турин',
            'image' => ['src' => '', 'alt' => ''],
            'images' => [['src' => '/model-first.webp', 'alt' => 'Model']],
        ]);

        verify($item['image']['src'])->equals('/model-first.webp');
    }

    public function testPickCategoryPreviewPrefersPopularThenOrder(): void
    {
        $picked = $this->builder->pickCategoryPreviewDocuments([
            ['slug' => 'a', '_isPopular' => false],
            ['slug' => 'b', '_isPopular' => true],
            ['slug' => 'c', '_isPopular' => false],
            ['slug' => 'd', '_isPopular' => true],
        ], 3);

        verify(array_column($picked, 'slug'))->equals(['b', 'd', 'a']);
    }
}
