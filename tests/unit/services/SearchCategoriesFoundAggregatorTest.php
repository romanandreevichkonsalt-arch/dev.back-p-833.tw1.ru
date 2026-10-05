<?php

namespace tests\unit\services;

use app\services\search\SearchCategoriesFoundAggregator;
use Codeception\Test\Unit;

class SearchCategoriesFoundAggregatorTest extends Unit
{
    private SearchCategoriesFoundAggregator $aggregator;

    protected function _before(): void
    {
        $this->aggregator = new SearchCategoriesFoundAggregator();
    }

    public function testAggregatesBySubcategory(): void
    {
        $products = [
            ['subcategory' => 'divany', 'subcategoryLabel' => 'Диваны'],
            ['subcategory' => 'divany', 'type' => 'Диваны'],
            ['subcategory' => 'kresla', 'subcategoryLabel' => 'Кресла'],
        ];

        $items = $this->aggregator->aggregate($products);

        verify(count($items))->equals(2);
        verify($items[0]['id'])->equals('divany');
        verify($items[0]['matchCount'])->equals(2);
        verify($items[0]['href'])->equals('/catalog?subcategory=divany');
        verify($items[1]['id'])->equals('kresla');
        verify($items[0])->arrayHasKey('products');
        verify(count($items[0]['products']))->equals(2);
        verify($items[0]['products'][0])->arrayHasKey('title');
        verify(array_key_exists('swatches', $items[0]['products'][0]))->false();
    }

    public function testCategoryPreviewLimitsToThreeWithPopularFirst(): void
    {
        $products = [
            ['subcategory' => 'divany', 'subcategoryLabel' => 'Диваны', 'slug' => '1', 'title' => 'One', '_isPopular' => false],
            ['subcategory' => 'divany', 'subcategoryLabel' => 'Диваны', 'slug' => '2', 'title' => 'Two', '_isPopular' => true],
            ['subcategory' => 'divany', 'subcategoryLabel' => 'Диваны', 'slug' => '3', 'title' => 'Three', '_isPopular' => false],
            ['subcategory' => 'divany', 'subcategoryLabel' => 'Диваны', 'slug' => '4', 'title' => 'Four', '_isPopular' => true],
        ];

        $items = $this->aggregator->aggregate($products);

        verify(count($items[0]['products']))->equals(3);
        verify(array_column($items[0]['products'], 'slug'))->equals(['2', '4', '1']);
    }

    public function testSkipsProductsWithoutSubcategory(): void
    {
        $items = $this->aggregator->aggregate([
            ['title' => 'Без категории'],
        ]);

        verify($items)->empty();
    }
}
