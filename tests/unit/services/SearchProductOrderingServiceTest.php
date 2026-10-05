<?php

namespace tests\unit\services;

use app\services\search\SearchCatalogPriorityService;
use app\services\search\SearchProductOrderingService;
use Codeception\Test\Unit;

class SearchProductOrderingServiceTest extends Unit
{
    /**
     * @return array<string, mixed>
     */
    private function document(int $index, int $collectionId, string $collectionName, int $price): array
    {
        return [
            '_productId' => 1000 + $index,
            '_collectionId' => $collectionId,
            '_collectionSortName' => mb_strtolower($collectionName),
            '_directionSlug' => 'line-1',
            '_priceAmount' => $price,
            '_fabricSort' => 0,
            '_colorSort' => 0,
            'collection' => $collectionName,
            'id' => 'sku-' . $index,
        ];
    }

    public function testRoundRobinCollectionsAlphabeticallyByLowestPrice(): void
    {
        $documents = [
            $this->document(0, 1, 'адриано', 200),
            $this->document(1, 1, 'адриано', 100),
            $this->document(2, 2, 'брайтон', 300),
            $this->document(3, 2, 'брайтон', 150),
            $this->document(4, 3, 'артемида', 120),
        ];

        $service = new SearchProductOrderingService(
            priorityService: new SearchCatalogPriorityService()
        );

        $ordered = $service->orderAll($documents);

        verify(array_column($ordered, 'id'))->equals([
            'sku-1',
            'sku-4',
            'sku-3',
            'sku-0',
            'sku-2',
        ]);
    }

    public function testPageTwoSkipsPrioritySlots(): void
    {
        $documents = [
            $this->document(0, 1, 'адриано', 100),
            $this->document(1, 2, 'брайтон', 100),
            $this->document(2, 1, 'адриано', 200),
            $this->document(3, 2, 'брайтон', 200),
        ];

        $service = new SearchProductOrderingService(
            priorityService: new SearchCatalogPriorityService()
        );

        $page1 = $service->orderPage($documents, 1, 2);
        $page2 = $service->orderPage($documents, 2, 2);

        verify(array_column($page1, 'id'))->equals(['sku-0', 'sku-1']);
        verify(array_column($page2, 'id'))->equals(['sku-2', 'sku-3']);
    }

    public function testPrioritySkusPrependedInConfiguredOrderOnPageOne(): void
    {
        $documents = [
            array_merge($this->document(0, 1, 'адриано', 100), ['_productId' => 501]),
            array_merge($this->document(1, 2, 'брайтон', 100), ['_productId' => 502]),
            array_merge($this->document(2, 3, 'артемида', 100), ['_productId' => 503]),
        ];

        $priorityService = new class ([503, 501]) extends SearchCatalogPriorityService {
            /** @param list<int> $ids */
            public function __construct(private array $ids)
            {
            }

            public function getOrderedSampleProductIds(?array $restrictToProductIds = null, ?array $restrictToDirectionIds = null): array
            {
                $ids = $this->ids;
                if ($restrictToProductIds !== null && $restrictToProductIds !== []) {
                    $ids = array_values(array_filter(
                        $ids,
                        static fn (int $id): bool => in_array($id, $restrictToProductIds, true)
                    ));
                }

                return $ids;
            }
        };

        $service = new SearchProductOrderingService(priorityService: $priorityService);

        verify(array_column($service->orderPage($documents, 1, 3), '_productId'))->equals([503, 501, 502]);
        verify($service->orderPage($documents, 2, 3))->equals([]);
    }
}
