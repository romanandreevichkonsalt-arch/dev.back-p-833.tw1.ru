<?php

namespace tests\unit\services;

use app\services\catalog\CatalogListingSort;
use Codeception\Test\Unit;

class CatalogListingSortTest extends Unit
{
    private CatalogListingSort $listingSort;

    protected function _before(): void
    {
        $this->listingSort = new CatalogListingSort();
    }

    public function testPriceAscUsesAmountBeforeFabricColor(): void
    {
        $cheap = $this->listingSort->buildRoundRobinRow([
            'id' => 1,
            'model_id' => 10,
            'collection_id' => 5,
            'price_amount' => 100,
            'collection_sort' => 1,
            'model_sort' => 1,
            'fabric_sort' => 9,
            'color_sort' => 9,
        ], 'price_asc');

        $expensive = $this->listingSort->buildRoundRobinRow([
            'id' => 2,
            'model_id' => 20,
            'collection_id' => 6,
            'price_amount' => 500,
            'collection_sort' => 2,
            'model_sort' => 2,
            'fabric_sort' => 1,
            'color_sort' => 1,
        ], 'price_asc');

        verify($cheap['intraSort'] < $expensive['intraSort'])->true();
        verify($this->listingSort->orderGroupsByBucketHead('price_asc'))->true();
        verify($this->listingSort->orderGroupsByBucketHead('default'))->false();
        verify($this->listingSort->orderGroupsByBucketHead('popular'))->false();
        verify($this->listingSort->effectiveRoundRobinSort('popular'))->equals('default');
    }

    public function testPopularUsesSameRoundRobinSortKeyAsDefault(): void
    {
        $row = [
            'id' => 1,
            'model_id' => 10,
            'collection_id' => 5,
            'price_amount' => 100,
            'collection_sort' => 1,
            'model_sort' => 1,
            'fabric_sort' => 1,
            'color_sort' => 1,
            'is_popular' => true,
        ];

        verify($this->listingSort->buildRoundRobinRow($row, $this->listingSort->effectiveRoundRobinSort('popular'))['intraSort'])
            ->equals($this->listingSort->buildRoundRobinRow($row, 'default')['intraSort']);
    }

    public function testAlphabetUsesCollectionPriceRoundRobin(): void
    {
        verify($this->listingSort->useCollectionPriceRoundRobin('alphabet'))->true();
        verify($this->listingSort->useCollectionPriceRoundRobin('default'))->false();
        verify($this->listingSort->useCollectionPriceRoundRobin('popular'))->false();
        verify($this->listingSort->useCollectionPriceRoundRobin('price_asc'))->false();
    }

    public function testDefaultSortUsesPriceBeforeFabricColor(): void
    {
        $cheap = $this->listingSort->buildRoundRobinRow([
            'id' => 1,
            'model_id' => 10,
            'collection_id' => 5,
            'price_amount' => 100,
            'collection_sort' => 1,
            'model_sort' => 1,
            'fabric_sort' => 9,
            'color_sort' => 9,
        ], 'default');

        $expensive = $this->listingSort->buildRoundRobinRow([
            'id' => 2,
            'model_id' => 10,
            'collection_id' => 5,
            'price_amount' => 500,
            'collection_sort' => 1,
            'model_sort' => 1,
            'fabric_sort' => 1,
            'color_sort' => 1,
        ], 'default');

        verify($cheap['intraSort'] < $expensive['intraSort'])->true();
    }
}
