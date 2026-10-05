<?php

namespace app\services\search;

use app\models\CatalogProduct;
use app\models\SearchCategory;
use app\models\SearchFrequentQuery;
use app\services\cache\ApiResponseCache;
use app\services\catalog\CatalogService;
use app\services\dealer\DealerPricingService;
use app\services\promotion\CatalogPromotionPricing;
use app\services\promotion\CatalogPromotionResolver;
use app\services\catalog\ProductRoundRobinSorter;
use app\services\content\ContentFallbackTrait;
use app\services\content\JsonContentService;

class SearchService
{
    use ContentFallbackTrait;

    public const DEFAULT_PRODUCT_LIMIT = 5;

    private JsonContentService $jsonContent;
    private CatalogService $catalog;
    private SearchQueryResolver $queryResolver;
    private SearchRanker $ranker;
    private SearchDocumentBuilder $documentBuilder;
    private SearchOftenSearchedMatcher $oftenSearchedMatcher;
    private SearchCategoriesFoundAggregator $categoriesFoundAggregator;
    private SearchRecommendedService $recommendedService;
    private SearchTitleMatcher $titleMatcher;

    private ProductRoundRobinSorter $roundRobin;
    private SearchProductOrderingService $productOrdering;
    private ApiResponseCache $apiCache;

    public function __construct(
        ?JsonContentService $jsonContent = null,
        ?CatalogService $catalog = null,
        ?ApiResponseCache $apiCache = null,
        ?SearchQueryResolver $queryResolver = null,
        ?SearchRanker $ranker = null,
        ?SearchDocumentBuilder $documentBuilder = null,
        ?SearchOftenSearchedMatcher $oftenSearchedMatcher = null,
        ?SearchCategoriesFoundAggregator $categoriesFoundAggregator = null,
        ?ProductRoundRobinSorter $roundRobin = null,
        ?SearchProductOrderingService $productOrdering = null,
        ?SearchRecommendedService $recommendedService = null,
        ?SearchTitleMatcher $titleMatcher = null,
    ) {
        $this->jsonContent = $jsonContent ?? new JsonContentService();
        $this->catalog = $catalog ?? new CatalogService();
        $this->apiCache = $apiCache ?? self::resolveApiResponseCache();
        $this->queryResolver = $queryResolver ?? new SearchQueryResolver();
        $this->ranker = $ranker ?? new SearchRanker($this->queryResolver);
        $this->documentBuilder = $documentBuilder ?? new SearchDocumentBuilder();
        $this->oftenSearchedMatcher = $oftenSearchedMatcher ?? new SearchOftenSearchedMatcher($this->queryResolver);
        $this->categoriesFoundAggregator = $categoriesFoundAggregator ?? new SearchCategoriesFoundAggregator();
        $this->roundRobin = $roundRobin ?? new ProductRoundRobinSorter();
        $this->productOrdering = $productOrdering ?? new SearchProductOrderingService();
        $this->recommendedService = $recommendedService ?? new SearchRecommendedService();
        $this->titleMatcher = $titleMatcher ?? new SearchTitleMatcher($this->queryResolver);
    }

    /**
     * @return array<string, mixed>
     */
    public function getBootstrap(): array
    {
        $match = $this->getMatchBootstrap();
        $recommendedPayload = $this->getRecommendedPayload();

        return array_merge($match, [
            'recommended' => $recommendedPayload['recommended'],
            'recommendedGroups' => $recommendedPayload['recommendedGroups'],
        ]);
    }

    /**
     * Данные для match / vocabulary без recommended (не тянуть тяжёлый bootstrap на каждый keystroke).
     *
     * @return array{frequent: list<string>, categories: list<array<string, mixed>>}
     */
    private function getMatchBootstrap(): array
    {
        if (!$this->hasSearchData()) {
            $fallback = $this->fallbackOrThrow(
                fn (): array => $this->jsonContent->getSearchBootstrap(),
                'Данные поиска не настроены.'
            );

            return [
                'frequent' => is_array($fallback['frequent'] ?? null) ? $fallback['frequent'] : [],
                'categories' => is_array($fallback['categories'] ?? null) ? $fallback['categories'] : [],
            ];
        }

        $config = \Yii::$app->params['apiCache'] ?? [];
        $ttl = (int)($config['searchBootstrapTtl'] ?? $config['catalogProductsTtl'] ?? 300);

        return $this->apiCache->get(
            'search',
            'match-bootstrap',
            fn (): array => [
                'frequent' => array_map(
                    static fn (SearchFrequentQuery $item): string => $item->query,
                    SearchFrequentQuery::find()
                        ->where(['is_active' => true])
                        ->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC])
                        ->all()
                ),
                'categories' => array_map(
                    static fn (SearchCategory $item): array => $item->toApiItem(),
                    SearchCategory::find()
                        ->where(['is_active' => true])
                        ->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC])
                        ->all()
                ),
            ],
            $ttl,
        );
    }

    /**
     * @param array{frequent: list<string>, categories: list<array<string, mixed>>} $bootstrap
     * @return list<string>
     */
    private function buildTokenVocabulary(array $bootstrap): array
    {
        $config = \Yii::$app->params['apiCache'] ?? [];
        $ttl = (int)($config['searchVocabularyTtl'] ?? $config['catalogProductsTtl'] ?? 300);

        return $this->apiCache->get(
            'search',
            'token-vocabulary',
            fn (): array => $this->queryResolver->buildTokenVocabulary(
                $bootstrap['frequent'] ?? [],
                $bootstrap['categories'] ?? [],
                $this->catalog->getSearchableProducts(),
            ),
            $ttl,
        );
    }

    /**
     * @param array{frequent: list<string>, categories: list<array<string, mixed>>} $bootstrap
     *
     * @return list<string>
     */
    private function buildLegacyVocabulary(array $bootstrap): array
    {
        $config = \Yii::$app->params['apiCache'] ?? [];
        $ttl = (int)($config['searchVocabularyTtl'] ?? $config['catalogProductsTtl'] ?? 300);

        return $this->apiCache->get(
            'search',
            'legacy-vocabulary',
            fn (): array => $this->queryResolver->buildVocabulary(
                $bootstrap['frequent'] ?? [],
                $bootstrap['categories'] ?? [],
                $this->catalog->getSearchableProducts(),
            ),
            $ttl,
        );
    }

    /**
     * @internal bench
     *
     * @return array{frequent: list<string>, categories: list<array<string, mixed>>}
     */
    public function getMatchBootstrapForBench(): array
    {
        return $this->getMatchBootstrap();
    }

    /**
     * @internal bench
     *
     * @param array{frequent: list<string>, categories: list<array<string, mixed>>} $bootstrap
     * @return list<string>
     */
    public function buildTokenVocabularyForBench(array $bootstrap, array $products): array
    {
        unset($products);

        return $this->buildTokenVocabulary($bootstrap);
    }

    /**
     * @param array{frequent: list<string>, categories: list<array<string, mixed>>} $bootstrap
     * @param list<array<string, mixed>> $products
     *
     * @return list<array<string, mixed>>
     *
     * @internal bench
     */
    public function matchProductsLegacyFallbackForBench(string $query, array $bootstrap, array $products): array
    {
        $legacyVocabulary = $this->buildLegacyVocabulary($bootstrap);
        $resolved = $this->queryResolver->resolve($query, $legacyVocabulary);

        return $this->ranker->matchProducts(
            $products,
            $resolved['raw'],
            $resolved['raw']
        );
    }

    /**
     * @return array{recommended: list<array<string, mixed>>, recommendedGroups: array<string, mixed>}
     */
    private function getRecommendedPayload(): array
    {
        if ($this->recommendedService->hasConfiguredProducts()) {
            return $this->recommendedService->buildBootstrapPayload();
        }

        if ($this->allowsJsonFallback()) {
            $fallback = $this->jsonContent->getSearchBootstrap();
            $recommended = $fallback['recommended'] ?? [];

            return [
                'recommended' => is_array($recommended) ? $recommended : [],
                'recommendedGroups' => [
                    'main' => is_array($recommended) ? $recommended : [],
                    'directions' => [],
                    'categories' => [],
                    'subcategories' => [],
                ],
            ];
        }

        return [
            'recommended' => [],
            'recommendedGroups' => [
                'main' => [],
                'directions' => [],
                'categories' => [],
                'subcategories' => [],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function search(string $query, int $limit = self::DEFAULT_PRODUCT_LIMIT, ?\app\models\User $dealer = null): array
    {
        $limit = max(1, min($limit, SearchRanker::MAX_PRODUCTS));
        $bootstrap = $this->getMatchBootstrap();
        $products = $this->catalog->getSearchableProducts();
        $frequent = $bootstrap['frequent'] ?? [];

        $tokenVocabulary = $this->buildTokenVocabulary($bootstrap);
        $matchResult = $this->titleMatcher->match($products, $query, $tokenVocabulary);
        $matchedProducts = $matchResult['products'];

        if ($matchedProducts === []) {
            $legacyVocabulary = $this->buildLegacyVocabulary($bootstrap);
            $resolved = $this->queryResolver->resolve($query, $legacyVocabulary);
            $matchedProducts = $this->ranker->matchProducts(
                $products,
                $resolved['raw'],
                $resolved['raw']
            );
            $matchResult['raw'] = $resolved['raw'];
            $matchResult['correction'] = $resolved['correction'];
            $matchResult['matchType'] = $matchedProducts === []
                ? SearchTitleMatcher::MATCH_NONE
                : SearchTitleMatcher::MATCH_SIMILAR;
            $matchResult['matchedQuery'] = $resolved['raw'];
        }

        $matchedProducts = $this->productOrdering->orderAll($matchedProducts);

        $responseSlice = array_slice($matchedProducts, 0, $limit);

        $categoriesFound = $this->categoriesFoundAggregator->aggregate($matchedProducts);

        if ($dealer !== null && $dealer->isDealer()) {
            $pricingContext = $this->createDealerSearchPricingContext(
                $this->collectDealerPricingSlugsFromDocuments($responseSlice, $categoriesFound)
            );
            $responseSlice = $this->applyDealerPricingToSearchDocuments($responseSlice, $dealer, $pricingContext);
            $categoriesFound = $this->applyDealerPricingToCategoryFoundPreviews(
                $categoriesFound,
                $dealer,
                $pricingContext
            );
        }

        $responseProducts = array_map(
            fn (array $product): array => $this->documentBuilder->toPublicProduct($product),
            $responseSlice
        );

        return [
            'query' => $matchResult['raw'],
            'correction' => $matchResult['correction'],
            'matchType' => $matchResult['matchType'],
            'matchedQuery' => $matchResult['matchedQuery'],
            'products' => $responseProducts,
            'oftenSearched' => $this->oftenSearchedMatcher->match($matchResult['raw'], $frequent),
            'categoriesFound' => $categoriesFound,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function searchProducts(
        string $query,
        int $page = 1,
        int $perPage = 24,
        string $sort = 'default',
        ?\app\models\User $dealer = null,
    ): array {
        $page = max(1, $page);
        $perPage = min(100, max(1, $perPage));
        $products = $this->catalog->getSearchableProducts();
        $bootstrap = $this->getMatchBootstrap();
        $frequent = $bootstrap['frequent'] ?? [];
        $tokenVocabulary = $this->buildTokenVocabulary($bootstrap);
        $matchResult = $this->titleMatcher->match($products, $query, $tokenVocabulary);
        $matchedProducts = $matchResult['products'];

        if ($matchedProducts === []) {
            $legacyVocabulary = $this->buildLegacyVocabulary($bootstrap);
            $resolved = $this->queryResolver->resolve($query, $legacyVocabulary);
            $matchedProducts = $this->ranker->matchProducts(
                $products,
                $resolved['raw'],
                $resolved['raw']
            );
            $matchResult['raw'] = $resolved['raw'];
            $matchResult['correction'] = $resolved['correction'];
            $matchResult['matchType'] = $matchedProducts === []
                ? SearchTitleMatcher::MATCH_NONE
                : SearchTitleMatcher::MATCH_SIMILAR;
            $matchResult['matchedQuery'] = $resolved['raw'];
        }

        if ($sort === 'default' || $sort === '') {
            $pageItems = $this->productOrdering->orderPage($matchedProducts, $page, $perPage);
        } else {
            $sort = str_replace('-', '_', $sort);
            $matchedProducts = $this->roundRobin->sortSearchDocuments($matchedProducts, $sort);
            $pageItems = array_slice($matchedProducts, ($page - 1) * $perPage, $perPage);
        }

        if ($dealer !== null && $dealer->isDealer()) {
            $pricingContext = $this->createDealerSearchPricingContext(
                $this->collectDealerPricingSlugsFromDocuments($pageItems, [])
            );
            $pageItems = $this->applyDealerPricingToSearchDocuments($pageItems, $dealer, $pricingContext);
        }

        $total = count($matchedProducts);

        return [
            'query' => $matchResult['raw'],
            'correction' => $matchResult['correction'],
            'matchType' => $matchResult['matchType'],
            'matchedQuery' => $matchResult['matchedQuery'],
            'items' => array_map(
                fn (array $product): array => $this->documentBuilder->toPublicProduct($product),
                $pageItems
            ),
            'meta' => [
                'total' => $total,
                'page' => $page,
                'perPage' => $perPage,
                'sort' => $sort,
            ],
        ];
    }

    private static function resolveApiResponseCache(): ApiResponseCache
    {
        if (\Yii::$container->has(ApiResponseCache::class)) {
            return \Yii::$container->get(ApiResponseCache::class);
        }

        return new ApiResponseCache();
    }

    private function hasSearchData(): bool
    {
        try {
            return SearchFrequentQuery::find()->exists() || SearchCategory::find()->exists();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param list<array<string, mixed>> $documents
     * @param list<array<string, mixed>> $categoriesFound
     * @return list<string>
     */
    private function collectDealerPricingSlugsFromDocuments(array $documents, array $categoriesFound): array
    {
        $slugs = [];
        foreach ($documents as $document) {
            $slug = trim((string)($document['slug'] ?? $document['id'] ?? ''));
            if ($slug !== '') {
                $slugs[$slug] = $slug;
            }
        }
        foreach ($categoriesFound as $category) {
            foreach ((array)($category['products'] ?? []) as $product) {
                if (!is_array($product)) {
                    continue;
                }
                $slug = trim((string)($product['slug'] ?? $product['id'] ?? ''));
                if ($slug !== '') {
                    $slugs[$slug] = $slug;
                }
            }
        }

        return array_values($slugs);
    }

    /**
     * @param list<string> $slugs
     * @return array{
     *     productsBySlug: array<string, CatalogProduct>,
     *     promotionResolver: CatalogPromotionResolver,
     *     pricingService: DealerPricingService
     * }
     */
    private function createDealerSearchPricingContext(array $slugs): array
    {
        if ($slugs === []) {
            return [
                'productsBySlug' => [],
                'promotionResolver' => new CatalogPromotionResolver(),
                'pricingService' => new DealerPricingService(),
            ];
        }

        /** @var array<string, CatalogProduct> $productsBySlug */
        $productsBySlug = CatalogProduct::find()
            ->where(['slug' => $slugs, 'is_active' => true])
            ->with($this->searchPricingRelations())
            ->indexBy('slug')
            ->all();

        return [
            'productsBySlug' => $productsBySlug,
            'promotionResolver' => new CatalogPromotionResolver(),
            'pricingService' => new DealerPricingService(),
        ];
    }

    /**
     * @return list<string>
     */
    private function searchPricingRelations(): array
    {
        return [
            'badge.image',
            'catalogModel.modelPrices',
            'fabricColor.fabricCollection',
        ];
    }

    /**
     * @param list<array<string, mixed>> $documents
     * @param array{
     *     productsBySlug: array<string, CatalogProduct>,
     *     promotionResolver: CatalogPromotionResolver,
     *     pricingService: DealerPricingService
     * } $pricingContext
     * @return list<array<string, mixed>>
     */
    private function applyDealerPricingToSearchDocuments(
        array $documents,
        \app\models\User $dealer,
        array $pricingContext,
    ): array {
        return array_map(
            fn (array $document): array => $this->overlayDealerPricingOnSearchDocument(
                $document,
                $dealer,
                $pricingContext
            ),
            $documents
        );
    }

    /**
     * @param list<array<string, mixed>> $categoriesFound
     * @param array{
     *     productsBySlug: array<string, CatalogProduct>,
     *     promotionResolver: CatalogPromotionResolver,
     *     pricingService: DealerPricingService
     * } $pricingContext
     * @return list<array<string, mixed>>
     */
    private function applyDealerPricingToCategoryFoundPreviews(
        array $categoriesFound,
        \app\models\User $dealer,
        array $pricingContext,
    ): array {
        foreach ($categoriesFound as $index => $category) {
            $previews = [];
            foreach ((array)($category['products'] ?? []) as $product) {
                if (!is_array($product)) {
                    continue;
                }
                $previews[] = $this->overlayDealerPricingOnPublicProduct($product, $dealer, $pricingContext);
            }
            $categoriesFound[$index]['products'] = $previews;
        }

        return $categoriesFound;
    }

    /**
     * Только цены/бейдж для search; без swatches, images и прочего listing card.
     *
     * @param array<string, mixed> $document
     * @param array{
     *     productsBySlug: array<string, CatalogProduct>,
     *     promotionResolver: CatalogPromotionResolver,
     *     pricingService: DealerPricingService
     * } $pricingContext
     * @return array<string, mixed>
     */
    private function overlayDealerPricingOnSearchDocument(
        array $document,
        \app\models\User $dealer,
        array $pricingContext,
    ): array {
        $slug = trim((string)($document['slug'] ?? $document['id'] ?? ''));
        $product = $pricingContext['productsBySlug'][$slug] ?? null;
        if ($product === null) {
            return $document;
        }

        return $this->mergeDealerPricingIntoSearchDocument(
            $document,
            $product,
            $dealer,
            $pricingContext['pricingService'],
            $pricingContext['promotionResolver']
        );
    }

    /**
     * @param array<string, mixed> $product
     * @param array{
     *     productsBySlug: array<string, CatalogProduct>,
     *     promotionResolver: CatalogPromotionResolver,
     *     pricingService: DealerPricingService
     * } $pricingContext
     * @return array<string, mixed>
     */
    private function overlayDealerPricingOnPublicProduct(
        array $product,
        \app\models\User $dealer,
        array $pricingContext,
    ): array {
        $slug = trim((string)($product['slug'] ?? $product['id'] ?? ''));
        $model = $pricingContext['productsBySlug'][$slug] ?? null;
        if ($model === null) {
            return $product;
        }

        $document = $this->mergeDealerPricingIntoSearchDocument(
            $product,
            $model,
            $dealer,
            $pricingContext['pricingService'],
            $pricingContext['promotionResolver']
        );

        return [
            'id' => $document['id'] ?? $slug,
            'slug' => $document['slug'] ?? $slug,
            'title' => $document['title'] ?? '',
            'subcategory' => $document['subcategory'] ?? null,
            'collection' => $document['collection'] ?? null,
            'image' => $document['image'] ?? $product['image'] ?? null,
            'retailPrice' => $document['retailPrice'] ?? null,
            'priceDisplay' => $document['priceDisplay'] ?? null,
            'dealerPrice' => $document['dealerPrice'] ?? null,
            'dealerDiscountPercent' => $document['dealerDiscountPercent'] ?? null,
            'badge' => $document['badge'] ?? null,
            'href' => $document['href'] ?? $product['href'] ?? null,
        ];
    }

    /**
     * @param array<string, mixed> $document
     * @return array<string, mixed>
     */
    private function mergeDealerPricingIntoSearchDocument(
        array $document,
        CatalogProduct $product,
        \app\models\User $dealer,
        DealerPricingService $pricingService,
        CatalogPromotionResolver $promotionResolver,
    ): array {
        $pricing = $product->buildPricingApiPayload($dealer);
        $document['retailPrice'] = $pricing['retailPrice'] ?? null;
        $document['priceDisplay'] = $pricing['priceDisplay'] ?? null;
        if (isset($pricing['dealerPrice'])) {
            $document['dealerPrice'] = $pricing['dealerPrice'];
        } else {
            unset($document['dealerPrice']);
        }
        $retailPrice = isset($pricing['retailPrice']) ? (int)$pricing['retailPrice'] : null;
        $dealerPrice = isset($pricing['dealerPrice']) ? (int)$pricing['dealerPrice'] : null;
        $promotionPayload = is_array($pricing['catalogPromotion'] ?? null) ? $pricing['catalogPromotion'] : null;
        $personalPercent = isset($pricing['dealerDiscountPercent']) ? (int)$pricing['dealerDiscountPercent'] : null;
        $document['dealerDiscountPercent'] = CatalogPromotionPricing::resolveDisplayDiscountPercent(
            $retailPrice,
            $dealerPrice,
            $promotionPayload,
            $personalPercent,
        );
        $document['price'] = $pricing['priceDisplay'] ?? null;

        $badges = $product->buildApiBadgesPayload($dealer);
        if ($badges !== []) {
            $document['badge'] = $badges[0];
        }

        return $document;
    }
}
