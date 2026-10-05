<?php

namespace tests\unit\services;

use app\services\catalog\ProductRoundRobinSorter;
use Codeception\Test\Unit;

class ProductRoundRobinSorterTest extends Unit
{
    private ProductRoundRobinSorter $sorter;

    protected function _before(): void
    {
        $this->sorter = new ProductRoundRobinSorter();
    }

    public function testPriorityGroupKeysMoveModelsToFront(): void
    {
        $ordered = $this->sorter->sortIds([
            ['id' => 'a-1', 'groupKey' => 1, 'groupSort' => '1', 'intraSort' => '1'],
            ['id' => 'a-2', 'groupKey' => 1, 'groupSort' => '1', 'intraSort' => '2'],
            ['id' => 'b-1', 'groupKey' => 2, 'groupSort' => '2', 'intraSort' => '1'],
            ['id' => 'c-1', 'groupKey' => 3, 'groupSort' => '3', 'intraSort' => '1'],
        ], false, [3, 1]);

        verify($ordered[0])->equals('c-1');
        verify(array_search('b-1', $ordered, true))->greaterThan(array_search('c-1', $ordered, true));
    }

    public function testInterleavesModelsByColorRounds(): void
    {
        $ordered = $this->sorter->sortIds([
            ['id' => 'adriano-1', 'groupKey' => 1, 'groupSort' => '1', 'intraSort' => '1'],
            ['id' => 'adriano-4', 'groupKey' => 1, 'groupSort' => '1', 'intraSort' => '2'],
            ['id' => 'apollo-3', 'groupKey' => 2, 'groupSort' => '2', 'intraSort' => '1'],
            ['id' => 'apollo-10', 'groupKey' => 2, 'groupSort' => '2', 'intraSort' => '2'],
            ['id' => 'athena-5', 'groupKey' => 3, 'groupSort' => '3', 'intraSort' => '1'],
            ['id' => 'athena-2', 'groupKey' => 3, 'groupSort' => '3', 'intraSort' => '2'],
            ['id' => 'venice-2', 'groupKey' => 4, 'groupSort' => '4', 'intraSort' => '1'],
            ['id' => 'venice-28', 'groupKey' => 4, 'groupSort' => '4', 'intraSort' => '2'],
        ]);

        verify($ordered)->equals([
            'adriano-1',
            'apollo-10',
            'athena-5',
            'venice-28',
            'adriano-4',
            'apollo-3',
            'athena-2',
            'venice-2',
        ]);
    }

    public function testStaggerRotatesFirstColorByGroupPosition(): void
    {
        $ordered = $this->sorter->sortIds([
            ['id' => 'a-c1', 'groupKey' => 1, 'groupSort' => '1', 'intraSort' => '1'],
            ['id' => 'a-c2', 'groupKey' => 1, 'groupSort' => '1', 'intraSort' => '2'],
            ['id' => 'b-c1', 'groupKey' => 2, 'groupSort' => '2', 'intraSort' => '1'],
            ['id' => 'b-c2', 'groupKey' => 2, 'groupSort' => '2', 'intraSort' => '2'],
        ]);

        verify($ordered)->equals(['a-c1', 'b-c2', 'a-c2', 'b-c1']);
    }

    public function testPaginationSliceDoesNotRepeatSkus(): void
    {
        $ordered = $this->sorter->sortIds([
            ['id' => 'a1', 'groupKey' => 1, 'groupSort' => '1', 'intraSort' => '1'],
            ['id' => 'a2', 'groupKey' => 1, 'groupSort' => '1', 'intraSort' => '2'],
            ['id' => 'b1', 'groupKey' => 2, 'groupSort' => '2', 'intraSort' => '1'],
            ['id' => 'b2', 'groupKey' => 2, 'groupSort' => '2', 'intraSort' => '2'],
        ]);

        $page1 = array_slice($ordered, 0, 2);
        $page2 = array_slice($ordered, 2, 2);

        verify($page1)->equals(['a1', 'b2']);
        verify($page2)->equals(['a2', 'b1']);
        verify(array_unique(array_merge($page1, $page2)))->equals(array_merge($page1, $page2));
    }

    public function testSkipsExhaustedGroups(): void
    {
        $ordered = $this->sorter->sortIds([
            ['id' => 'a1', 'groupKey' => 1, 'groupSort' => '1', 'intraSort' => '1'],
            ['id' => 'a2', 'groupKey' => 1, 'groupSort' => '1', 'intraSort' => '2'],
            ['id' => 'b1', 'groupKey' => 2, 'groupSort' => '2', 'intraSort' => '1'],
        ]);

        verify($ordered)->equals(['a1', 'b1', 'a2']);
    }

    public function testDefersSameCollectionWhenAlternativeExists(): void
    {
        $ordered = $this->sorter->sortIds([
            ['id' => 'a1', 'groupKey' => 1, 'collectionKey' => 10, 'groupSort' => '1', 'intraSort' => '1'],
            ['id' => 'a2', 'groupKey' => 1, 'collectionKey' => 10, 'groupSort' => '1', 'intraSort' => '2'],
            ['id' => 'b1', 'groupKey' => 2, 'collectionKey' => 20, 'groupSort' => '2', 'intraSort' => '1'],
            ['id' => 'c1', 'groupKey' => 3, 'collectionKey' => 10, 'groupSort' => '3', 'intraSort' => '1'],
        ]);

        verify($ordered)->equals(['a1', 'b1', 'c1', 'a2']);
    }

    public function testOrderGroupsByBucketHeadWhenRequested(): void
    {
        $ordered = $this->sorter->sortIds([
            ['id' => 'cheap', 'groupKey' => 2, 'collectionKey' => 20, 'groupSort' => '2', 'intraSort' => '0100'],
            ['id' => 'cheap2', 'groupKey' => 2, 'collectionKey' => 20, 'groupSort' => '2', 'intraSort' => '0200'],
            ['id' => 'mid', 'groupKey' => 1, 'collectionKey' => 10, 'groupSort' => '1', 'intraSort' => '0150'],
            ['id' => 'mid2', 'groupKey' => 1, 'collectionKey' => 10, 'groupSort' => '1', 'intraSort' => '0250'],
        ], true);

        verify($ordered)->equals(['cheap', 'mid2', 'cheap2', 'mid']);
    }

    public function testEmptyInput(): void
    {
        verify($this->sorter->sortIds([]))->equals([]);
        verify($this->sorter->sortDocuments([]))->equals([]);
    }

    public function testSkipsIntraRotationWhenDisabled(): void
    {
        $ordered = $this->sorter->sortIds([
            ['id' => 'a-c1', 'groupKey' => 1, 'groupSort' => '1', 'intraSort' => '1'],
            ['id' => 'a-c2', 'groupKey' => 1, 'groupSort' => '1', 'intraSort' => '2'],
            ['id' => 'b-c1', 'groupKey' => 2, 'groupSort' => '2', 'intraSort' => '1'],
            ['id' => 'b-c2', 'groupKey' => 2, 'groupSort' => '2', 'intraSort' => '2'],
        ], false, [], false);

        verify($ordered)->equals(['a-c1', 'b-c1', 'a-c2', 'b-c2']);
    }

    public function testSortDocumentsUsesInternalKeys(): void
    {
        $ordered = $this->sorter->sortDocuments([
            ['id' => 'a2', '_groupKey' => 1, '_groupSort' => '1', '_intraSort' => '2'],
            ['id' => 'b1', '_groupKey' => 2, '_groupSort' => '2', '_intraSort' => '1'],
            ['id' => 'a1', '_groupKey' => 1, '_groupSort' => '1', '_intraSort' => '1'],
        ]);

        verify(array_column($ordered, 'id'))->equals(['a1', 'b1', 'a2']);
    }
}
