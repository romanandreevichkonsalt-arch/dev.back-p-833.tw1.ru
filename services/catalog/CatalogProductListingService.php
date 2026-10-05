<?php

namespace app\services\catalog;

use app\models\CatalogCollection;
use app\models\CatalogProduct;
use app\models\SearchCatalogPriorityModel;
use app\models\User;
use app\services\cache\ApiResponseCache;
use app\services\dealer\DealerPricingService;
use app\services\promotion\CatalogPromotionDealerListingPrice;
use app\services\promotion\CatalogPromotionPricing;
use app\services\promotion\CatalogPromotionResolver;
use app\services\search\SearchCatalogPriorityService;
use yii\db\ActiveQuery;
use yii\db\Expression;
use yii\db\Query;
use yii\web\BadRequestHttpException;

class CatalogProductListingService
{
    private ApiResponseCache $cache;
    private CatalogNavigationService $navigation;
    private ProductRoundRobinSorter $roundRobin;
    private CatalogListingSort $listingSort;
    private CatalogRequestParams $requestParams;

    public function __construct(
        ?ApiResponseCache $cache = null,
        ?CatalogNavigationService $navigation = null,
        ?ProductRoundRobinSorter $roundRobin = null,
        ?CatalogListingSort $listingSort = null,
        ?CatalogRequestParams $requestParams = null,
    ) {
        $this->cache = $cache ?? \Yii::$container->get(ApiResponseCache::class);
        $this->navigation = $navigation ?? \Yii::$container->get(CatalogNavigationService::class);
        $this->roundRobin = $roundRobin ?? new ProductRoundRobinSorter();
        $this->listingSort = $listingSort ?? new CatalogListingSort();
        $this->requestParams = $requestParams ?? \Yii::$container->get(CatalogRequestParams::class);
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function getProducts(array $params, ?\app\models\User $dealer = null): array
    {
        $params = $this->requestParams->normalize($params);
        $scope = CatalogScope::fromParams($params, false);
        $filters = $this->extractFilterParams($params);
        $sort = $this->normalizeSort((string)($params['sort'] ?? 'default'));
        $page = max(1, (int)($params['page'] ?? 1));
        $perPage = min(100, max(1, (int)($params['perPage'] ?? 24)));
        $collectionGroups = CatalogRequestParams::isCollectionGroupsEnabled($params);
        $itemsPerGroup = CatalogRequestParams::normalizeItemsPerGroup($params);

        $dealerDiscountPercent = $this->resolveDealerDiscountPercent($dealer);
        $useCache = !$collectionGroups
            && !$this->filtersAreApplied($filters)
            && $sort === 'default'
            && $page === 1
            && $perPage === 24;
        if ($useCache) {
            $config = \Yii::$app->params['apiCache'] ?? [];
            $scopeKey = $scope->scopeMode === 'all'
                ? 'all'
                : md5(json_encode($scope->appliedSlugs(), JSON_THROW_ON_ERROR));
            $dealerKey = $this->listingCacheDealerKey($dealerDiscountPercent);
            $cacheKey = 'products:' . $dealerKey . ':' . $scopeKey;

            return $this->cache->get(
                'catalog',
                $cacheKey,
                fn (): array => $this->buildProductsResponse(
                    $scope,
                    $filters,
                    $sort,
                    $page,
                    $perPage,
                    $dealer,
                    false,
                    CatalogRequestParams::DEFAULT_ITEMS_PER_GROUP
                ),
                (int)($config['catalogProductsTtl'] ?? 300)
            );
        }

        return $this->buildProductsResponse(
            $scope,
            $filters,
            $sort,
            $page,
            $perPage,
            $dealer,
            $collectionGroups,
            $itemsPerGroup
        );
    }

    /**
     * Число товаров по scope и фильтрам без загрузки items/facets (для HEAD).
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    /**
     * Листинг библиотеки 3D: одна SKU на коллекцию мебели, те же sort и scope category/subcategory.
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function getLibraryProducts(array $params): array
    {
        $params = $this->requestParams->normalize($params);
        $scope = CatalogScope::fromParams($params, false);
        $sort = $this->normalizeSort((string)($params['sort'] ?? 'default'));
        $page = max(1, (int)($params['page'] ?? 1));
        $perPage = min(100, max(1, (int)($params['perPage'] ?? 24)));

        $listQuery = $this->createScopedQuery($scope);
        $productIds = $this->pickOneProductIdPerCollection($listQuery, $sort);
        $total = count($productIds);
        $pageIds = array_slice($productIds, ($page - 1) * $perPage, $perPage);
        $products = $this->loadLibraryProductsByIds($pageIds);

        return [
            'items' => array_map(
                static fn (CatalogProduct $product): array => $product->toLibraryApiItem(),
                $products
            ),
            'meta' => $this->buildListingMeta($scope, $sort, $page, $perPage, $total, false, CatalogRequestParams::DEFAULT_ITEMS_PER_GROUP),
        ];
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function countLibraryProducts(array $params): array
    {
        $params = $this->requestParams->normalize($params);
        $scope = CatalogScope::fromParams($params, false);
        $sort = $this->normalizeSort((string)($params['sort'] ?? 'default'));
        $page = max(1, (int)($params['page'] ?? 1));
        $perPage = min(100, max(1, (int)($params['perPage'] ?? 24)));

        $listQuery = $this->createScopedQuery($scope);
        $total = count($this->pickOneProductIdPerCollection($listQuery, $sort));

        return $this->buildListingMeta(
            $scope,
            $sort,
            $page,
            $perPage,
            $total,
            false,
            CatalogRequestParams::DEFAULT_ITEMS_PER_GROUP
        );
    }

    public function countProducts(array $params, ?User $dealer = null): array
    {
        $params = $this->requestParams->normalize($params);
        $scope = CatalogScope::fromParams($params, false);
        $filters = $this->extractFilterParams($params);
        $sort = $this->normalizeSort((string)($params['sort'] ?? 'default'));
        $page = max(1, (int)($params['page'] ?? 1));
        $perPage = min(100, max(1, (int)($params['perPage'] ?? 24)));
        $collectionGroups = CatalogRequestParams::isCollectionGroupsEnabled($params);
        $itemsPerGroup = CatalogRequestParams::normalizeItemsPerGroup($params);
        $dealerDiscountPercent = $this->resolveDealerDiscountPercent($dealer);

        $listQuery = $this->createScopedQuery($scope);
        $this->applyFilters($listQuery, $filters, $dealerDiscountPercent);
        if ($collectionGroups) {
            $total = $this->countDistinctModelLines($listQuery);
        } else {
            $total = (int)(clone $listQuery)->count('DISTINCT p.id');
        }

        return $this->buildListingMeta(
            $scope,
            $sort,
            $page,
            $perPage,
            $total,
            $collectionGroups,
            $itemsPerGroup
        );
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function buildProductsResponse(
        CatalogScope $scope,
        array $filters,
        string $sort,
        int $page,
        int $perPage,
        ?User $dealer = null,
        bool $collectionGroups = false,
        int $itemsPerGroup = CatalogRequestParams::DEFAULT_ITEMS_PER_GROUP,
    ): array {
        $dealerDiscountPercent = $this->resolveDealerDiscountPercent($dealer);
        $facetQuery = $this->createScopedQuery($scope);
        $filtersPayload = $this->getFiltersPayloadCached($scope, $facetQuery, $dealerDiscountPercent);

        $listQuery = $this->createScopedQuery($scope);
        $this->applyFilters($listQuery, $filters, $dealerDiscountPercent);

        $listSourceRows = null;
        if ($collectionGroups || $this->listingMayPrependPriority($scope, $sort)) {
            $listSourceRows = $this->fetchRoundRobinSourceRows($listQuery, $dealerDiscountPercent);
        }

        if ($collectionGroups) {
            return $this->buildCollectionGroupedResponse(
                $scope,
                $listQuery,
                $filtersPayload,
                $sort,
                $page,
                $perPage,
                $itemsPerGroup,
                $dealer,
                $dealerDiscountPercent,
                $listSourceRows ?? []
            );
        }

        $total = (int)(clone $listQuery)->count('DISTINCT p.id');
        $listing = $this->takeProductsWithListingPriority(
            $scope,
            $listQuery,
            $sort,
            $page,
            $perPage,
            $dealerDiscountPercent,
            $filters,
            $listSourceRows
        );

        return [
            'breadcrumb' => $this->navigation->buildBreadcrumb($scope),
            'items' => $this->mapProductsToListingCards(
                $listing['products'],
                $listing['listingPriorityProductIds'],
                $dealer
            ),
            'filters' => $filtersPayload,
            'meta' => $this->buildListingMeta($scope, $sort, $page, $perPage, $total, false, $itemsPerGroup),
        ];
    }

    /**
     * @param array<string, mixed> $filtersPayload
     * @return array<string, mixed>
     */
    private function buildCollectionGroupedResponse(
        CatalogScope $scope,
        ActiveQuery $listQuery,
        array $filtersPayload,
        string $sort,
        int $page,
        int $perPage,
        int $itemsPerGroup,
        ?User $dealer,
        ?float $dealerDiscountPercent,
        array $sourceRows,
    ): array {
        $rowsByCollection = [];
        foreach ($sourceRows as $row) {
            $collectionId = (int)($row['collection_id'] ?? 0);
            if ($collectionId <= 0) {
                continue;
            }
            $rowsByCollection[$collectionId][] = $row;
        }

        if ($rowsByCollection === []) {
            return [
                'breadcrumb' => $this->navigation->buildBreadcrumb($scope),
                'items' => [],
                'filters' => $filtersPayload,
                'meta' => $this->buildListingMeta($scope, $sort, $page, $perPage, 0, true, $itemsPerGroup),
            ];
        }

        $collections = CatalogCollection::find()
            ->where(['id' => array_keys($rowsByCollection), 'is_active' => true])
            ->with('direction')
            ->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC])
            ->all();

        if ($sort === 'alphabet') {
            usort(
                $collections,
                static function (CatalogCollection $left, CatalogCollection $right): int {
                    return mb_strtolower($left->getDisplayName()) <=> mb_strtolower($right->getDisplayName());
                }
            );
        }

        $totalCollections = count($collections);
        $pageCollections = array_slice($collections, ($page - 1) * $perPage, $perPage);
        $groups = [];

        foreach ($pageCollections as $collection) {
            $collectionSource = $rowsByCollection[(int)$collection->id] ?? [];
            if ($collectionSource === []) {
                continue;
            }

            $sortedIds = $this->sortListingProductIds($collectionSource, $sort);
            $productIds = array_slice($sortedIds, 0, $itemsPerGroup);
            $products = $this->loadProductsByIds($productIds);

            $groups[] = $this->buildModelLineGroupPayload(
                $collection,
                count($collectionSource),
                array_map(
                    static fn (CatalogProduct $product): array => $product->toListingCard($dealer),
                    $products
                )
            );
        }

        return [
            'breadcrumb' => $this->navigation->buildBreadcrumb($scope),
            'items' => $groups,
            'filters' => $filtersPayload,
            'meta' => $this->buildListingMeta($scope, $sort, $page, $perPage, $totalCollections, true, $itemsPerGroup),
        ];
    }

    /**
     * @param list<array<string, mixed>> $productCards
     * @return array<string, mixed>
     */
    private function buildModelLineGroupPayload(CatalogCollection $collection, int $total, array $productCards): array
    {
        return [
            'slug' => (string)$collection->slug,
            'label' => (string)$collection->label,
            'title' => (string)$collection->title,
            'directionSlug' => $collection->direction?->slug,
            'href' => $collection->href,
            'total' => $total,
            'items' => $productCards,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildListingMeta(
        CatalogScope $scope,
        string $sort,
        int $page,
        int $perPage,
        int $total,
        bool $collectionGroups,
        int $itemsPerGroup,
    ): array {
        $meta = [
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'sort' => $sort,
            'scopeMode' => $scope->scopeMode,
            'layout' => $collectionGroups ? 'collectionGroups' : 'flat',
            'applied' => array_filter(
                $scope->appliedSlugs(),
                static fn (?string $value): bool => $value !== null && $value !== ''
            ),
        ];

        if ($collectionGroups) {
            $meta['itemsPerGroup'] = $itemsPerGroup;
        }

        return $meta;
    }

    private function countDistinctModelLines(ActiveQuery $listQuery): int
    {
        return (int)(clone $listQuery)
            ->select('p.collection_id')
            ->andWhere(['>', 'p.collection_id', 0])
            ->distinct()
            ->count('p.collection_id');
    }

    private function createScopedQuery(CatalogScope $scope): ActiveQuery
    {
        $query = CatalogProduct::find()
            ->alias('p')
            ->where(['p.is_active' => true, 'p.is_custom' => false])
            ->innerJoin('{{%catalog_subcategories}} sc', 'sc.id = p.subcategory_id');

        CatalogProductPublicVisibility::apply($query, 'p');

        if ($scope->direction !== null || $scope->collection !== null) {
            $query->innerJoin('{{%catalog_collections}} c', 'c.id = p.collection_id');
        }

        if ($scope->subcategory !== null) {
            $query->andWhere(['p.subcategory_id' => $scope->subcategory->id]);
        }
        if ($scope->category !== null) {
            $query->andWhere(['sc.category_id' => $scope->category->id]);
        }
        if ($scope->collection !== null) {
            $query->andWhere(['p.collection_id' => $scope->collection->id]);
        }
        if ($scope->direction !== null) {
            $query->andWhere(['c.direction_id' => $scope->direction->id]);
        }

        return $query;
    }

    /**
     * @return array{products: CatalogProduct[], listingPriorityProductIds: list<int>}
     */
    private function takeProductsWithListingPriority(
        CatalogScope $scope,
        ActiveQuery $listQuery,
        string $sort,
        int $page,
        int $perPage,
        ?float $dealerDiscountPercent,
        array $requestFilters = [],
        ?array $listSourceRows = null,
    ): array {
        $page = max(1, $page);
        $perPage = max(1, $perPage);
        $directionId = (int)($scope->direction?->id ?? 0);

        if ($directionId <= 0 || !$this->shouldPrependListingPriority($sort)) {
            return [
                'products' => $this->takeRoundRobin(
                    $listQuery,
                    $sort,
                    $perPage,
                    ($page - 1) * $perPage,
                    $dealerDiscountPercent,
                    [],
                    $scope,
                    $requestFilters,
                    $listSourceRows
                ),
                'listingPriorityProductIds' => [],
            ];
        }

        $priorityIds = $this->resolveListingPriorityProductIds($listQuery, $directionId);
        if ($priorityIds === []) {
            return [
                'products' => $this->takeRoundRobin(
                    $listQuery,
                    $sort,
                    $perPage,
                    ($page - 1) * $perPage,
                    $dealerDiscountPercent,
                    [],
                    $scope,
                    $requestFilters,
                    $listSourceRows
                ),
                'listingPriorityProductIds' => [],
            ];
        }

        $priorityProducts = $this->loadProductsByIds($priorityIds);
        $priorityCount = count($priorityProducts);

        $priorityIdSet = array_fill_keys($priorityIds, true);
        $bodySourceRows = $listSourceRows !== null
            ? array_values(array_filter(
                $listSourceRows,
                static fn (array $row): bool => !isset($priorityIdSet[(int)($row['id'] ?? 0)])
            ))
            : null;

        $bodyQuery = clone $listQuery;
        $bodyQuery->andWhere(['not in', 'p.id', $priorityIds]);

        if ($page <= 1) {
            $take = max(0, $perPage - $priorityCount);

            return [
                'products' => array_merge(
                    $priorityProducts,
                    $this->takeRoundRobin(
                        $bodyQuery,
                        $sort,
                        $take,
                        0,
                        $dealerDiscountPercent,
                        [],
                        $scope,
                        $requestFilters,
                        $bodySourceRows
                    )
                ),
                'listingPriorityProductIds' => $priorityIds,
            ];
        }

        $bodyOffset = ($page - 1) * $perPage - $priorityCount;
        if ($bodyOffset < 0) {
            $bodyOffset = 0;
        }

        return [
            'products' => $this->takeRoundRobin(
                $bodyQuery,
                $sort,
                $perPage,
                $bodyOffset,
                $dealerDiscountPercent,
                [],
                $scope,
                $requestFilters,
                $bodySourceRows
            ),
            'listingPriorityProductIds' => [],
        ];
    }

    private function shouldPrependListingPriority(string $sort): bool
    {
        return in_array($sort, ['default', 'popular'], true);
    }

    private function listingMayPrependPriority(CatalogScope $scope, string $sort): bool
    {
        $directionId = (int)($scope->direction?->id ?? 0);

        return $directionId > 0 && $this->shouldPrependListingPriority($sort);
    }

    /**
     * @return array<string, mixed>
     */
    private function getFiltersPayloadCached(
        CatalogScope $scope,
        ActiveQuery $facetQuery,
        ?float $dealerDiscountPercent,
    ): array {
        $config = \Yii::$app->params['apiCache'] ?? [];
        $cacheKey = 'filters:' . $this->listingCacheScopeKey($scope) . ':' . $this->listingCacheDealerKey($dealerDiscountPercent);

        return $this->cache->get(
            'catalog',
            $cacheKey,
            fn (): array => $this->buildFiltersPayload($facetQuery, $dealerDiscountPercent),
            (int)($config['catalogProductsTtl'] ?? 300)
        );
    }

    /**
     * @param list<int|string> $priorityGroupKeys
     * @return list<int>
     */
    private function getSortedListingProductIdsCached(
        ActiveQuery $listQuery,
        CatalogScope $scope,
        array $requestFilters,
        string $sort,
        ?float $dealerDiscountPercent,
        array $priorityGroupKeys = [],
    ): array {
        $config = \Yii::$app->params['apiCache'] ?? [];
        $cacheKey = 'order:' . $this->listingCacheScopeKey($scope)
            . ':' . $sort
            . ':' . $this->listingCacheDealerKey($dealerDiscountPercent)
            . ':' . md5(json_encode($requestFilters, JSON_THROW_ON_ERROR))
            . ($priorityGroupKeys === [] ? '' : ':' . md5(json_encode($priorityGroupKeys, JSON_THROW_ON_ERROR)));

        /** @var list<int> */
        return $this->cache->get(
            'catalog',
            $cacheKey,
            function () use ($listQuery, $sort, $dealerDiscountPercent, $priorityGroupKeys): array {
                $rows = $this->fetchRoundRobinSourceRows($listQuery, $dealerDiscountPercent);

                return $this->sortListingProductIds($rows, $sort, $priorityGroupKeys);
            },
            (int)($config['catalogProductsTtl'] ?? 300)
        );
    }

    private function listingCacheScopeKey(CatalogScope $scope): string
    {
        return md5(json_encode($scope->appliedSlugs(), JSON_THROW_ON_ERROR));
    }

    private function listingCacheDealerKey(?float $dealerDiscountPercent): string
    {
        if ($dealerDiscountPercent === null) {
            return 'guest';
        }

        return 'd' . rtrim(rtrim(sprintf('%.4F', $dealerDiscountPercent), '0'), '.');
    }

    /**
     * @return list<int>
     */
    private function resolveListingPriorityProductIds(ActiveQuery $listQuery, int $directionId): array
    {
        $ordered = (new SearchCatalogPriorityService())->getOrderedSampleProductIds(null, [$directionId]);
        if ($ordered === []) {
            return [];
        }

        $inScope = array_map(
            static fn ($id): int => (int)$id,
            (clone $listQuery)->andWhere(['p.id' => $ordered])->select('p.id')->column()
        );
        if ($inScope === []) {
            return [];
        }

        $allowed = array_fill_keys($inScope, true);

        return array_values(array_filter(
            $ordered,
            static fn (int $id): bool => isset($allowed[$id])
        ));
    }

    private function directionHasListingPriorities(CatalogScope $scope): bool
    {
        $directionId = (int)($scope->direction?->id ?? 0);
        if ($directionId <= 0) {
            return false;
        }

        if (\Yii::$app->db->schema->getTableSchema(SearchCatalogPriorityModel::tableName(), true) === null) {
            return false;
        }

        return SearchCatalogPriorityModel::find()
            ->where(['direction_id' => $directionId])
            ->andWhere(['>', 'sample_catalog_product_id', 0])
            ->exists();
    }

    /**
     * @return CatalogProduct[]
     */
    public function takeRoundRobin(
        ActiveQuery $listQuery,
        string $sort,
        int $limit,
        int $offset = 0,
        ?float $dealerDiscountPercent = null,
        array $priorityGroupKeys = [],
        ?CatalogScope $scope = null,
        array $requestFilters = [],
        ?array $preloadedSourceRows = null,
    ): array {
        $limit = max(0, $limit);
        if ($limit === 0) {
            return [];
        }

        if ($preloadedSourceRows !== null) {
            $ids = $this->sortListingProductIds($preloadedSourceRows, $sort, $priorityGroupKeys);
        } elseif ($scope !== null) {
            $ids = $this->getSortedListingProductIdsCached(
                $listQuery,
                $scope,
                $requestFilters,
                $sort,
                $dealerDiscountPercent,
                $priorityGroupKeys
            );
        } else {
            $ids = $this->sortListingProductIds(
                $this->fetchRoundRobinSourceRows($listQuery, $dealerDiscountPercent),
                $sort,
                $priorityGroupKeys
            );
        }

        return $this->loadProductsByIds(array_slice($ids, max(0, $offset), $limit));
    }

    /**
     * @param list<array<string, mixed>> $sourceRows
     * @param list<int|string> $priorityGroupKeys
     * @return list<int>
     */
    private function sortListingProductIds(array $sourceRows, string $sort, array $priorityGroupKeys = []): array
    {
        if ($sourceRows === []) {
            return [];
        }

        if ($this->listingSort->useCollectionPriceRoundRobin($sort)) {
            $rows = array_map(
                fn (array $row): array => $this->listingSort->buildCollectionPriceRoundRobinRow(
                    $row,
                    (string)($row['direction_slug'] ?? '')
                ),
                $sourceRows
            );

            return array_map('intval', $this->roundRobin->sortIds($rows, false, $priorityGroupKeys, false));
        }

        $roundRobinSort = $this->listingSort->effectiveRoundRobinSort($sort);
        $rows = array_map(
            fn (array $row): array => $this->listingSort->buildRoundRobinRow($row, $roundRobinSort),
            $sourceRows
        );

        return array_map('intval', $this->roundRobin->sortIds(
            $rows,
            $this->listingSort->orderGroupsByBucketHead($sort),
            $priorityGroupKeys
        ));
    }

    /**
     * @param CatalogProduct[] $products
     * @param list<int> $listingPriorityProductIds
     * @return list<array<string, mixed>>
     */
    private function mapProductsToListingCards(
        array $products,
        array $listingPriorityProductIds,
        ?User $dealer,
    ): array {
        $priorityOrder = [];
        foreach ($listingPriorityProductIds as $index => $productId) {
            $priorityOrder[(int)$productId] = $index + 1;
        }

        $items = [];
        foreach ($products as $product) {
            $card = $product->toListingCard($dealer);
            $priorityRank = $priorityOrder[(int)$product->id] ?? null;
            if ($priorityRank !== null) {
                $card['listingPriorityOrder'] = $priorityRank;
            }
            $items[] = $card;
        }

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchRoundRobinSourceRows(ActiveQuery $listQuery, ?float $dealerDiscountPercent = null): array
    {
        $pricing = new DealerPricingService();
        $promotionResolver = $dealerDiscountPercent !== null ? new CatalogPromotionResolver() : null;
        $rows = (clone $listQuery)
            ->select([
                'id' => 'p.id',
                'model_id' => 'p.model_id',
                'collection_id' => 'p.collection_id',
                'price_amount' => 'p.price_amount',
                'is_popular' => 'p.is_popular',
                'sort_order' => 'p.sort_order',
                'created_at' => 'p.created_at',
                'collection_sort' => 'c_rr.sort_order',
                'collection_display_name' => 'c_rr.name',
                'collection_title' => 'c_rr.title',
                'direction_slug' => 'd_rr.slug',
                'model_sort' => 'm_rr.sort_order',
                'fabric_sort' => 'fcol_rr.sort_order',
                'color_sort' => 'fc_rr.sort_order',
                'badge_variant' => 'badge_rr.variant',
            ])
            ->leftJoin('{{%catalog_collections}} c_rr', 'c_rr.id = p.collection_id')
            ->leftJoin('{{%catalog_directions}} d_rr', 'd_rr.id = c_rr.direction_id')
            ->leftJoin('{{%catalog_models}} m_rr', 'm_rr.id = p.model_id')
            ->leftJoin('{{%catalog_fabric_collection_colors}} fc_rr', 'fc_rr.id = p.fabric_color_id')
            ->leftJoin('{{%catalog_fabric_collections}} fcol_rr', 'fcol_rr.id = fc_rr.fabric_collection_id')
            ->leftJoin('{{%catalog_badges}} badge_rr', 'badge_rr.id = p.badge_id')
            ->asArray()
            ->all();

        $mapped = [];
        foreach ($rows as $row) {
            $id = (int)$row['id'];
            if (isset($mapped[$id])) {
                continue;
            }
            if ($dealerDiscountPercent !== null && $row['price_amount'] !== null) {
                $retailAmount = (int)$row['price_amount'];
                $modelId = (int)($row['model_id'] ?? 0);
                $promotion = $promotionResolver?->findForProduct($id, $modelId);
                if ($promotion !== null) {
                    $promoPrice = CatalogPromotionPricing::resolveUnitPrice($retailAmount, $promotion);
                    if ($promoPrice !== null) {
                        $row['price_amount'] = $promoPrice;
                        $mapped[$id] = $row;
                        continue;
                    }
                }
                $row['price_amount'] = $pricing->applyDiscountToPriceAmount($retailAmount, $dealerDiscountPercent);
            }
            $mapped[$id] = $row;
        }

        return array_values($mapped);
    }

    /**
     * @return list<array{id: int, groupKey: int, collectionKey: int, groupSort: string, intraSort: string}>
     */
    private function fetchRoundRobinRows(ActiveQuery $listQuery, string $sort = 'default', ?float $dealerDiscountPercent = null): array
    {
        $mapped = [];
        foreach ($this->fetchRoundRobinSourceRows($listQuery, $dealerDiscountPercent) as $row) {
            $id = (int)$row['id'];
            $mapped[$id] = array_merge(
                $this->listingSort->buildRoundRobinRow($row, $sort),
                ['_collectionId' => (int)($row['collection_id'] ?? 0)]
            );
        }

        return array_values($mapped);
    }

    /**
     * @return list<int>
     */
    private function pickOneProductIdPerCollection(ActiveQuery $listQuery, string $sort): array
    {
        $sourceRows = $this->fetchRoundRobinSourceRows($listQuery, null);
        if ($sourceRows === []) {
            return [];
        }

        $collectionByProductId = [];
        foreach ($sourceRows as $row) {
            $collectionByProductId[(int)$row['id']] = (int)($row['collection_id'] ?? 0);
        }

        $sortedIds = $this->sortListingProductIds($sourceRows, $sort);

        $seenCollections = [];
        $uniqueIds = [];
        foreach ($sortedIds as $id) {
            $productId = (int)$id;
            $collectionId = $collectionByProductId[$productId] ?? 0;
            if ($collectionId <= 0 || isset($seenCollections[$collectionId])) {
                continue;
            }

            $seenCollections[$collectionId] = true;
            $uniqueIds[] = $productId;
        }

        return $uniqueIds;
    }

    /**
     * @param list<int> $ids
     * @return CatalogProduct[]
     */
    private function loadLibraryProductsByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $products = CatalogProduct::find()
            ->alias('p')
            ->where(['p.id' => $ids])
            ->with($this->libraryProductRelations())
            ->all();

        $byId = [];
        foreach ($products as $product) {
            $byId[(int)$product->id] = $product;
        }

        $ordered = [];
        foreach ($ids as $id) {
            if (isset($byId[$id])) {
                $ordered[] = $byId[$id];
            }
        }

        return $ordered;
    }

    /**
     * @param list<int> $ids
     * @return CatalogProduct[]
     */
    private function loadProductsByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $products = CatalogProduct::find()
            ->alias('p')
            ->where(['p.id' => $ids])
            ->with($this->productRelations())
            ->all();

        $byId = [];
        foreach ($products as $product) {
            $byId[(int)$product->id] = $product;
        }

        $ordered = [];
        foreach ($ids as $id) {
            if (isset($byId[$id])) {
                $ordered[] = $byId[$id];
            }
        }

        return $ordered;
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    /**
     * @param array<string, mixed> $filters
     */
    private function filtersAreApplied(array $filters): bool
    {
        foreach ($filters as $value) {
            if ($value === null || $value === false) {
                continue;
            }
            if (is_array($value) && $value === []) {
                continue;
            }

            return true;
        }

        return false;
    }

    private function extractFilterParams(array $params): array
    {
        $colors = self::normalizeFilterList($params['color'] ?? null);
        $textures = self::normalizeFilterList($params['texture'] ?? null);

        return [
            'priceMin' => $this->nullableInt($params['priceMin'] ?? null),
            'priceMax' => $this->nullableInt($params['priceMax'] ?? null),
            'color' => $colors,
            'texture' => $textures,
            'widthMin' => $this->nullableInt($params['widthMin'] ?? null),
            'widthMax' => $this->nullableInt($params['widthMax'] ?? null),
            'heightMin' => $this->nullableInt($params['heightMin'] ?? null),
            'heightMax' => $this->nullableInt($params['heightMax'] ?? null),
            'depthMin' => $this->nullableInt($params['depthMin'] ?? null),
            'depthMax' => $this->nullableInt($params['depthMax'] ?? null),
            'sleepingPlace' => $this->parseSleepingPlaceParam($params['sleepingPlace'] ?? null),
            'foldable' => $this->parseBooleanFilterParam($params['foldable'] ?? null),
        ];
    }

    /**
     * @return list<string>
     */
    private static function normalizeFilterList(mixed $value): array
    {
        if (is_array($value)) {
            $items = [];
            foreach ($value as $item) {
                if (!is_string($item) && !is_numeric($item)) {
                    continue;
                }
                $items = array_merge($items, CatalogRequestParams::expandFilterValue((string)$item));
            }

            return array_values(array_unique(array_filter(
                $items,
                static fn (string $v): bool => $v !== ''
            )));
        }

        if ($value === null || $value === '') {
            return [];
        }

        return CatalogRequestParams::expandFilterValue((string)$value);
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int)$value;
    }

    private function normalizeSort(string $sort): string
    {
        $allowed = ['default', 'price_asc', 'price_desc', 'popular', 'new', 'alphabet'];
        if (!in_array($sort, $allowed, true)) {
            throw new BadRequestHttpException('Недопустимое значение sort.');
        }

        return $sort;
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function applyFilters(ActiveQuery $query, array $filters, ?float $dealerDiscountPercent = null): void
    {
        $priceColumn = $dealerDiscountPercent !== null
            ? CatalogPromotionDealerListingPrice::buildEffectivePriceExpression($dealerDiscountPercent)
            : 'p.price_amount';

        if ($filters['priceMin'] !== null) {
            $query->andWhere(['>=', $priceColumn, $filters['priceMin']]);
        }
        if ($filters['priceMax'] !== null) {
            $query->andWhere(['<=', $priceColumn, $filters['priceMax']]);
        }
        if ($filters['widthMin'] !== null) {
            $query->andWhere(['>=', 'p.width_mm', $filters['widthMin']]);
        }
        if ($filters['widthMax'] !== null) {
            $query->andWhere(['<=', 'p.width_mm', $filters['widthMax']]);
        }
        if ($filters['heightMin'] !== null) {
            $query->andWhere(['>=', 'p.height_mm', $filters['heightMin']]);
        }
        if ($filters['heightMax'] !== null) {
            $query->andWhere(['<=', 'p.height_mm', $filters['heightMax']]);
        }
        if ($filters['depthMin'] !== null) {
            $query->andWhere(['>=', 'p.depth_mm', $filters['depthMin']]);
        }
        if ($filters['depthMax'] !== null) {
            $query->andWhere(['<=', 'p.depth_mm', $filters['depthMax']]);
        }
        if ($filters['sleepingPlace'] === true) {
            $query->andWhere(['p.has_sleeping_place' => true]);
        }
        if ($filters['foldable'] === true && $this->productTableHasFoldableColumn()) {
            $query->andWhere(['p.is_foldable' => true]);
        }

        if ($filters['color'] !== []) {
            $query->innerJoin('{{%catalog_fabric_collection_colors}} fc_filter', 'fc_filter.id = p.fabric_color_id')
                ->innerJoin('{{%catalog_colors}} cc_filter', 'cc_filter.id = fc_filter.color_id')
                ->andWhere(['cc_filter.slug' => $filters['color'], 'cc_filter.is_active' => true]);
        }

        if ($filters['texture'] !== []) {
            $query->innerJoin('{{%catalog_fabric_collection_colors}} fc_tex', 'fc_tex.id = p.fabric_color_id')
                ->innerJoin('{{%catalog_fabric_collections}} fcol_tex', 'fcol_tex.id = fc_tex.fabric_collection_id')
                ->andWhere(['fcol_tex.texture' => $filters['texture']]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildFiltersPayload(ActiveQuery $scopeQuery, ?float $dealerDiscountPercent = null): array
    {
        $pricing = new DealerPricingService();
        $idQuery = clone $scopeQuery;
        $productIds = $idQuery->select('p.id')->column();

        if ($productIds === []) {
            return [
                'price' => null,
                'colors' => [],
                'textures' => [],
                'functions' => [],
                'dimensions' => null,
            ];
        }

        if ($dealerDiscountPercent !== null) {
            $dealerExpr = CatalogPromotionDealerListingPrice::buildEffectivePriceExpression($dealerDiscountPercent);
            $priceRow = (new Query())
                ->from(['p' => '{{%catalog_products}}'])
                ->where(['p.id' => $productIds])
                ->andWhere(['not', ['p.price_amount' => null]])
                ->select([
                    'min' => new Expression('MIN(' . $dealerExpr->expression . ')'),
                    'max' => new Expression('MAX(' . $dealerExpr->expression . ')'),
                ])
                ->one();
        } else {
            $priceRow = (new Query())
                ->from('{{%catalog_products}}')
                ->where(['id' => $productIds])
                ->andWhere(['not', ['price_amount' => null]])
                ->select([
                    'min' => new Expression('MIN(price_amount)'),
                    'max' => new Expression('MAX(price_amount)'),
                ])
                ->one();
        }

        $dimensionRow = (new Query())
            ->from('{{%catalog_products}}')
            ->where(['id' => $productIds])
            ->select([
                'widthMin' => new Expression('MIN(width_mm)'),
                'widthMax' => new Expression('MAX(width_mm)'),
                'heightMin' => new Expression('MIN(height_mm)'),
                'heightMax' => new Expression('MAX(height_mm)'),
                'depthMin' => new Expression('MIN(depth_mm)'),
                'depthMax' => new Expression('MAX(depth_mm)'),
            ])
            ->one();

        $colorRows = (new Query())
            ->from(['p' => '{{%catalog_products}}'])
            ->innerJoin('{{%catalog_fabric_collection_colors}} fc', 'fc.id = p.fabric_color_id')
            ->innerJoin('{{%catalog_colors}} cc', 'cc.id = fc.color_id')
            ->where(['p.id' => $productIds, 'cc.is_active' => true])
            ->groupBy(['cc.id'])
            ->select([
                'id' => 'cc.slug',
                'label' => 'cc.label',
                'hexColor' => 'cc.hex_color',
                'count' => new Expression('COUNT(DISTINCT p.id)'),
            ])
            ->orderBy(['cc.sort_order' => SORT_ASC, 'cc.label' => SORT_ASC])
            ->all();

        $textureRows = (new Query())
            ->from(['p' => '{{%catalog_products}}'])
            ->innerJoin('{{%catalog_fabric_collection_colors}} fc', 'fc.id = p.fabric_color_id')
            ->innerJoin('{{%catalog_fabric_collections}} fcol', 'fcol.id = fc.fabric_collection_id')
            ->where(['p.id' => $productIds])
            ->andWhere(['not', ['fcol.texture' => null]])
            ->andWhere(['<>', 'fcol.texture', ''])
            ->groupBy(['fcol.texture'])
            ->select([
                'texture' => 'fcol.texture',
                'count' => new Expression('COUNT(DISTINCT p.id)'),
            ])
            ->orderBy(['fcol.texture' => SORT_ASC])
            ->all();

        $sleepingCount = (int)(new Query())
            ->from('{{%catalog_products}}')
            ->where(['id' => $productIds, 'has_sleeping_place' => true])
            ->count();

        $foldableCount = 0;
        if ($this->productTableHasFoldableColumn()) {
            $foldableCount = (int)(new Query())
                ->from('{{%catalog_products}}')
                ->where(['id' => $productIds, 'is_foldable' => true])
                ->count();
        }

        $functions = [];
        if ($sleepingCount > 0) {
            $functions[] = [
                'id' => 'sleeping',
                'label' => 'Спальное место',
                'count' => $sleepingCount,
            ];
        }
        if ($foldableCount > 0) {
            $functions[] = [
                'id' => 'foldable',
                'label' => 'Раскладной',
                'count' => $foldableCount,
            ];
        }

        return [
            'price' => $priceRow && $priceRow['min'] !== null
                ? ['min' => (int)$priceRow['min'], 'max' => (int)$priceRow['max']]
                : null,
            'colors' => array_map(static fn (array $row): array => [
                'id' => (string)$row['id'],
                'label' => (string)$row['label'],
                'hexColor' => $row['hexColor'] !== null ? (string)$row['hexColor'] : null,
                'count' => (int)$row['count'],
            ], $colorRows),
            'textures' => array_map(static fn (array $row): array => [
                'id' => (string)$row['texture'],
                'label' => (string)$row['texture'],
                'count' => (int)$row['count'],
            ], $textureRows),
            'functions' => $functions,
            'dimensions' => $this->buildDimensionsFilterPayload($dimensionRow),
        ];
    }

    /**
     * @param array<string, mixed>|false $dimensionRow
     * @return array<string, mixed>|null
     */
    private function buildDimensionsFilterPayload(array|false $dimensionRow): ?array
    {
        if ($dimensionRow === false) {
            return null;
        }

        $width = $this->rangeFromRow($dimensionRow, 'widthMin', 'widthMax');
        $height = $this->rangeFromRow($dimensionRow, 'heightMin', 'heightMax');
        $depth = $this->rangeFromRow($dimensionRow, 'depthMin', 'depthMax');

        if ($width === null && $height === null && $depth === null) {
            return null;
        }

        return [
            'width' => $width,
            'height' => $height,
            'depth' => $depth,
            'unit' => 'mm',
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array{min: int, max: int}|null
     */
    private function rangeFromRow(array $row, string $minKey, string $maxKey): ?array
    {
        if ($row[$minKey] === null || $row[$maxKey] === null) {
            return null;
        }

        return ['min' => (int)$row[$minKey], 'max' => (int)$row[$maxKey]];
    }

    private function parseSleepingPlaceParam(mixed $value): ?bool
    {
        return $this->parseBooleanFilterParam($value);
    }

    private function parseBooleanFilterParam(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = mb_strtolower(trim((string)$value), 'UTF-8');

        if (in_array($normalized, ['0', 'false', 'no', 'нет'], true)) {
            return false;
        }

        if (in_array($normalized, ['1', 'true', 'yes', 'да'], true)) {
            return true;
        }

        return null;
    }

    private function resolveDealerDiscountPercent(?User $dealer): ?float
    {
        if ($dealer === null || !$dealer->isDealer()) {
            return null;
        }

        return (new DealerPricingService())->getEffectiveDiscountPercent($dealer);
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * @return array<string, mixed>
     */
    private function libraryProductRelations(): array
    {
        return [
            'image',
            'collection.direction',
            'subcategory.category',
            'badge.image',
            'catalogModel.file3d',
            'catalogModel.modelImages.media',
        ];
    }

    private function productRelations(): array
    {
        return [
            'image',
            'collection',
            'subcategory',
            'badge.image',
            'catalogModel.modelPrices',
            'catalogModel.category',
            'catalogModel.subcategory',
            'catalogModel.collection',
            'catalogModel.badge.image',
            'catalogModel.modelImages.media',
            'catalogModel.fabricCollections.activeColors.catalogColor.swatchMedia',
            'catalogModel.fabricCollections.activeColors.swatchMedia',
            'fabricColor.catalogColor.swatchMedia',
            'fabricColor.fabricCollection',
        ];
    }

    private function productTableHasFoldableColumn(): bool
    {
        static $hasColumn = null;
        if ($hasColumn === null) {
            $schema = \Yii::$app->db->schema->getTableSchema(CatalogProduct::tableName(), true);
            $hasColumn = $schema !== null && $schema->getColumn('is_foldable') !== null;
        }

        return $hasColumn;
    }
}
