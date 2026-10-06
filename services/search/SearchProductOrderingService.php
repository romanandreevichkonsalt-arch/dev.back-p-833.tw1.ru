<?php

namespace app\services\search;

use app\services\catalog\CatalogListingSort;
use app\services\catalog\ProductRoundRobinSorter;

/**
 * Выдача поиска (default): образцы SKU → round-robin по коллекциям (A→Z) × цена ↑.
 */
class SearchProductOrderingService
{
    public const LINE1_DIRECTION_SLUG = 'line-1';

    public function __construct(
        private readonly ProductRoundRobinSorter $roundRobin = new ProductRoundRobinSorter(),
        private readonly SearchCatalogPriorityService $priorityService = new SearchCatalogPriorityService(),
        private readonly CatalogListingSort $listingSort = new CatalogListingSort(),
    ) {
    }

    /**
     * Полный порядок для подсказки / categoriesFound (без пагинации).
     *
     * @param list<array<string, mixed>> $documents
     * @return list<array<string, mixed>>
     */
    public function orderAll(array $documents): array
    {
        if ($documents === []) {
            return [];
        }

        $priorityDocs = $this->resolvePriorityDocuments($documents);
        $body = $this->sortCollectionPriceRoundRobin(
            $this->excludeDocumentsByProductId($documents, $this->productIdsFromDocuments($priorityDocs))
        );

        return array_merge($priorityDocs, $body);
    }

    /**
     * Страница листинга: приоритетные SKU только на page 1.
     *
     * @param list<array<string, mixed>> $documents
     * @return list<array<string, mixed>>
     */
    public function orderPage(array $documents, int $page, int $perPage): array
    {
        if ($documents === []) {
            return [];
        }

        $page = max(1, $page);
        $perPage = max(1, $perPage);

        $priorityDocs = $this->resolvePriorityDocuments($documents);
        $priorityCount = count($priorityDocs);
        $bodyDocs = $this->excludeDocumentsByProductId($documents, $this->productIdsFromDocuments($priorityDocs));

        if ($page <= 1) {
            $take = max(0, $perPage - $priorityCount);
            // Stop round-robin after enough body rows (autocomplete / page 1).
            $body = $this->sortCollectionPriceRoundRobin($bodyDocs, $take);

            return array_merge($priorityDocs, $body);
        }

        $bodyOffset = ($page - 1) * $perPage - $priorityCount;
        if ($bodyOffset < 0) {
            $bodyOffset = 0;
        }

        // Need a prefix long enough to slice the requested page.
        $body = $this->sortCollectionPriceRoundRobin($bodyDocs, $bodyOffset + $perPage);

        return array_slice($body, $bodyOffset, $perPage);
    }

    /**
     * @param list<array<string, mixed>> $documents
     * @return list<array<string, mixed>>
     */
    /**
     * @param list<array<string, mixed>> $documents
     * @return list<array<string, mixed>>
     */
    private function sortCollectionPriceRoundRobin(array $documents, ?int $maxResults = null): array
    {
        if ($documents === [] || $maxResults === 0) {
            return [];
        }

        $rows = [];
        foreach ($documents as $index => $document) {
            $rows[] = $this->buildCollectionRow($document, $index);
        }

        $ordered = [];
        foreach ($this->roundRobin->sortIds($rows, false, [], false, $maxResults) as $index) {
            $ordered[] = $documents[(int)$index];
        }

        return $ordered;
    }

    /**
     * @param array<string, mixed> $document
     * @return array<string, mixed>
     */
    private function buildCollectionRow(array $document, int $index): array
    {
        $collectionId = (int)($document['_collectionId'] ?? 0);
        $productId = (int)($document['_productId'] ?? 0);
        $groupKey = $collectionId > 0 ? $collectionId : ('pid-' . ($productId > 0 ? $productId : $index));

        $row = $this->listingSort->buildRoundRobinRowFromSearchDocument($document, 'price_asc', $index);
        $row['id'] = $index;
        $row['groupKey'] = $groupKey;
        $row['collectionKey'] = $groupKey;

        $name = (string)($document['_collectionSortName'] ?? '');
        if ($name === '') {
            $name = mb_strtolower(trim((string)($document['collection'] ?? '')));
        }

        $row['groupSort'] = sprintf(
            '%01d-%s-%010d',
            $this->directionRank($document),
            $name,
            max(0, $collectionId)
        );

        return $row;
    }

    /**
     * @param array<string, mixed> $document
     */
    private function directionRank(array $document): int
    {
        if ((string)($document['_directionSlug'] ?? '') === self::LINE1_DIRECTION_SLUG) {
            return 0;
        }

        return 1;
    }

    /**
     * @param list<array<string, mixed>> $documents
     * @return list<array<string, mixed>>
     */
    private function resolvePriorityDocuments(array $documents): array
    {
        $byProductId = [];
        foreach ($documents as $document) {
            $productId = (int)($document['_productId'] ?? 0);
            if ($productId > 0) {
                $byProductId[$productId] = $document;
            }
        }

        if ($byProductId === []) {
            return [];
        }

        $directionIds = [];
        foreach ($documents as $document) {
            $directionId = (int)($document['_directionId'] ?? 0);
            if ($directionId > 0) {
                $directionIds[$directionId] = $directionId;
            }
        }

        $ordered = [];
        foreach ($this->priorityService->getOrderedSampleProductIds(
            array_keys($byProductId),
            $directionIds !== [] ? array_values($directionIds) : null
        ) as $productId) {
            if (isset($byProductId[$productId])) {
                $ordered[] = $byProductId[$productId];
            }
        }

        return $ordered;
    }

    /**
     * @param list<array<string, mixed>> $documents
     * @param list<int> $productIds
     * @return list<array<string, mixed>>
     */
    private function excludeDocumentsByProductId(array $documents, array $productIds): array
    {
        if ($productIds === []) {
            return $documents;
        }

        $skip = array_fill_keys($productIds, true);
        $filtered = [];
        foreach ($documents as $document) {
            $productId = (int)($document['_productId'] ?? 0);
            if ($productId > 0 && isset($skip[$productId])) {
                continue;
            }
            $filtered[] = $document;
        }

        return $filtered;
    }

    /**
     * @param list<array<string, mixed>> $documents
     * @return list<int>
     */
    private function productIdsFromDocuments(array $documents): array
    {
        $ids = [];
        foreach ($documents as $document) {
            $productId = (int)($document['_productId'] ?? 0);
            if ($productId > 0) {
                $ids[] = $productId;
            }
        }

        return $ids;
    }
}
