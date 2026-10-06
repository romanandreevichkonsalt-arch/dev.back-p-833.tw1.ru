<?php

namespace app\services\catalog;

use app\models\CatalogCollection;
use app\models\CatalogDirection;
use app\models\CatalogProduct;
use app\models\CatalogSubcategory;
use app\models\MediaFile;
use app\services\cache\ApiResponseCache;
use app\services\catalog\CatalogTaxonomyService;
use app\services\content\ContentFallbackTrait;
use app\services\content\JsonContentService;

class CatalogService
{
    use ContentFallbackTrait;

    private JsonContentService $jsonContent;
    private ApiResponseCache $cache;
    private CatalogTaxonomyService $taxonomy;

    public function __construct(?ApiResponseCache $cache = null, ?CatalogTaxonomyService $taxonomy = null)
    {
        $this->jsonContent = new JsonContentService();
        $this->cache = $cache ?? \Yii::$container->get(ApiResponseCache::class);
        $this->taxonomy = $taxonomy ?? new CatalogTaxonomyService();
    }

    public function getMenu(): array
    {
        $config = \Yii::$app->params['apiCache'] ?? [];

        return $this->cache->get(
            'catalog',
            'menu',
            fn (): array => $this->buildMenu(),
            (int)($config['catalogMenuTtl'] ?? 900)
        );
    }

    private function buildMenu(): array
    {
        if (!$this->hasCatalogData()) {
            return $this->fallbackOrThrow(
                fn (): array => $this->jsonContent->getCatalogMenu(),
                'Каталог не настроен.'
            );
        }

        $directions = CatalogDirection::find()
            ->where(['is_active' => true])
            ->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC])
            ->with([
                'collections' => static function ($query) {
                    $query->andWhere(['is_active' => true])->orderBy(['sort_order' => SORT_ASC]);
                },
            ])
            ->all();

        $collections = CatalogCollection::find()
            ->where(['is_active' => true])
            ->orderBy(['sort_order' => SORT_ASC])
            ->with(['image', 'direction', 'collectionImages.media'])
            ->all();

        $directionPayloads = [];
        foreach ($directions as $direction) {
            $categories = $this->taxonomy->buildCategoriesForDirection($direction);
            $directionPayloads[] = [
                'id' => $direction->slug,
                'label' => $direction->label,
                'categories' => $categories,
                'subcategories' => $this->dedupeSubcategories($this->flattenSubcategories($categories)),
            ];
        }

        $defaultCategories = $directionPayloads[0]['categories'] ?? [];
        $groups = $this->categoriesToMenuGroups($defaultCategories);

        return [
            'directions' => $directionPayloads,
            'groups' => $groups,
            'categories' => $defaultCategories,
            'collections' => array_map(
                fn (CatalogCollection $collection): array => $this->collectionToFrontendCard($collection),
                $collections,
            ),
            'modelLines' => array_map([$this, 'modelLineToApi'], $collections),
            'menuCollections' => array_map([$this, 'collectionToApi'], $collections),
        ];
    }

    /**
     * @param list<array{slug: string, label: string, subcategories: list<array{slug: string, label: string}>}> $categories
     * @return list<array{id: string, label: string, subcategories: list<array{id: string, label: string}>}>
     */
    private function categoriesToMenuGroups(array $categories): array
    {
        return array_map(static fn (array $category): array => [
            'id' => $category['slug'],
            'label' => $category['label'],
            'subcategories' => array_map(static fn (array $sub): array => [
                'id' => $sub['slug'],
                'label' => $sub['label'],
            ], $category['subcategories']),
        ], $categories);
    }

    /**
     * @param list<array{slug: string, label: string, subcategories: list<array{slug: string, label: string}>}> $categories
     * @return list<array{id: string, label: string}>
     */
    private function flattenSubcategories(array $categories): array
    {
        $result = [];
        foreach ($categories as $category) {
            foreach ($category['subcategories'] as $subcategory) {
                $result[] = [
                    'id' => $subcategory['slug'],
                    'label' => $subcategory['label'],
                ];
            }
        }

        return $result;
    }

    /**
     * @param list<array{id: string, label: string}> $subcategories
     * @return list<array{id: string, label: string}>
     */
    private function dedupeSubcategories(array $subcategories): array
    {
        $seen = [];
        $result = [];
        foreach ($subcategories as $subcategory) {
            $id = $subcategory['id'];
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $result[] = $subcategory;
        }

        return $result;
    }

    private function collectionToFrontendCard(CatalogCollection $collection): array
    {
        $api = $this->collectionToApi($collection);

        return [
            'slug' => $collection->slug,
            'label' => $collection->label ?: 'Коллекция',
            'title' => $collection->title,
            'titleUppercase' => (bool)$collection->title_uppercase,
            'description' => $collection->description,
            'href' => $collection->href,
            'ctaLabel' => $collection->cta_label,
            'image' => $api['image'],
            'images' => $api['images'],
            'imagePosition' => $collection->image_position ?? 'center',
        ];
    }

    private function modelLineToApi(CatalogCollection $collection): array
    {
        $payload = $this->collectionToApi($collection);
        $payload['directionSlug'] = $collection->direction?->slug;
        $payload['modelLineSlug'] = $collection->slug;

        return $payload;
    }

    public function getMenuProducts(string $subcategorySlug, ?string $collectionSlug = null, ?int $perPage = null): array
    {
        $subcategorySlug = trim($subcategorySlug);
        if ($subcategorySlug === '') {
            return ['items' => []];
        }

        $collectionSlug = $collectionSlug !== null ? trim($collectionSlug) : '';
        $perPage = $this->normalizeMenuProductsPerPage($perPage);
        $config = \Yii::$app->params['apiCache'] ?? [];
        $cacheKey = 'menu-products:' . $subcategorySlug
            . ($collectionSlug !== '' ? ':' . $collectionSlug : '')
            . ':p' . $perPage;

        return $this->cache->get(
            'catalog',
            $cacheKey,
            fn (): array => $this->buildMenuProducts($subcategorySlug, $collectionSlug !== '' ? $collectionSlug : null, $perPage),
            (int)($config['catalogProductsTtl'] ?? 300)
        );
    }

    private function normalizeMenuProductsPerPage(?int $perPage): int
    {
        $default = (int)(\Yii::$app->params['catalogMenuProductsPerPage'] ?? 4);
        $max = (int)(\Yii::$app->params['catalogMenuProductsMaxPerPage'] ?? 4);
        $min = (int)(\Yii::$app->params['catalogMenuProductsMinPerPage'] ?? 1);
        $max = max($min, $max);
        $default = min($max, max($min, $default));

        if ($perPage === null) {
            return $default;
        }

        return min($max, max($min, $perPage));
    }

    private function buildMenuProducts(string $subcategorySlug, ?string $collectionSlug = null, int $perPage = 4): array
    {
        if (!$this->hasCatalogData()) {
            return $this->fallbackOrThrow(
                fn (): array => $this->jsonContent->getCatalogProducts($subcategorySlug),
                'Каталог не настроен.'
            );
        }

        $subcategory = CatalogSubcategory::find()->where(['slug' => $subcategorySlug, 'is_active' => true])->one();
        if ($subcategory === null) {
            return ['items' => []];
        }

        $products = CatalogProduct::find()
            ->alias('p')
            ->where(['p.subcategory_id' => $subcategory->id, 'p.is_active' => true, 'p.is_custom' => false])
            ->orderBy(['p.sort_order' => SORT_ASC, 'p.id' => SORT_ASC]);

        if ($collectionSlug !== null && $collectionSlug !== '') {
            $products->innerJoin('{{%catalog_collections}} c', 'c.id = p.collection_id')
                ->andWhere(['c.slug' => $collectionSlug, 'c.is_active' => true]);
        }

        $products = $products
            ->with([
                'image',
                'video',
                'collection.direction',
                'subcategory.category',
                'badge.image',
                'layout',
                'catalogModel.modelPrices',
                'catalogModel.category',
                'catalogModel.subcategory',
                'catalogModel.collection',
                'catalogModel.badge.image',
                'catalogModel.layout',
                'catalogModel.video',
                'catalogModel.modelImages.media',
                'catalogModel.modelInteriorImages.media',
                'catalogModel.modelDimensionImages.media',
                'fabricColor.catalogColor.colorImages.media',
                'fabricColor.catalogColor.swatchMedia',
                'fabricColor.fabricCollection',
            ])
            ->all();

        $sorter = new ProductRoundRobinSorter();
        $rows = [];
        foreach ($products as $product) {
            $rows[] = [
                'id' => (int)$product->id,
                'groupKey' => $product->getListingGroupKey(),
                'collectionKey' => (int)($product->collection_id ?? 0) > 0
                    ? (int)$product->collection_id
                    : -1 * (int)$product->id,
                'groupSort' => $product->getListingGroupSort(),
                'intraSort' => $product->getListingIntraSort(),
            ];
        }
        $byId = [];
        foreach ($products as $product) {
            $byId[(int)$product->id] = $product;
        }
        $ordered = [];
        foreach ($sorter->sortIds($rows) as $id) {
            if (isset($byId[(int)$id])) {
                $ordered[] = $byId[(int)$id];
            }
            if (count($ordered) >= $perPage) {
                break;
            }
        }

        return [
            'items' => array_map(static fn (CatalogProduct $p): array => $p->toMenuApiItem(), $ordered),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getSearchableProducts(): array
    {
        $config = \Yii::$app->params['apiCache'] ?? [];
        $softTtl = (int)($config['searchIndexSoftTtl'] ?? $config['catalogProductsTtl'] ?? 600);
        $hardTtl = (int)($config['searchIndexHardTtl'] ?? 86400);

        return $this->cache->getSoft(
            'catalog',
            // v3: lean SQL build + soft/hard TTL (stale-while-revalidate)
            'searchable-products-lite-v3',
            fn (): array => $this->buildSearchableProducts(),
            max(60, $softTtl),
            max(300, $hardTtl),
        );
    }

    /**
     * Synchronous rebuild + store (warm/cron). Bypasses soft-TTL stale return.
     *
     * @return array<int, array<string, mixed>>
     */
    public function rebuildSearchableProductsIndex(): array
    {
        $config = \Yii::$app->params['apiCache'] ?? [];
        $hardTtl = (int)($config['searchIndexHardTtl'] ?? 86400);
        $docs = $this->buildSearchableProducts();
        $this->cache->putSoft('catalog', 'searchable-products-lite-v3', $docs, max(300, $hardTtl));

        // Drop refresh lock if a soft-expire request spawned us.
        $lockKey = sprintf(
            'api:catalog:v%d:searchable-products-lite-v3:refresh-lock',
            $this->cache->getVersion()
        );
        \Yii::$app->cache->delete($lockKey);

        return $docs;
    }

    private function buildSearchableProducts(): array
    {
        if (!$this->hasCatalogData()) {
            return $this->fallbackOrThrow(
                fn (): array => $this->jsonContent->getSearchableProducts(),
                'Каталог не настроен.'
            );
        }

        return (new \app\services\search\SearchLiteIndexBuilder())->build();
    }

    private function hasCatalogData(): bool
    {
        try {
            return CatalogDirection::find()->exists();
        } catch (\Throwable) {
            return false;
        }
    }

    private function collectionToApi(CatalogCollection $collection): array
    {
        $images = $collection->getImagesApiPayload();
        $primaryImage = $images[0] ?? null;

        return [
            'id' => $collection->slug,
            'groupId' => $collection->direction?->slug,
            'directionId' => $collection->direction?->slug,
            'label' => $collection->label,
            'name' => $collection->getDisplayName(),
            'title' => $collection->title,
            'titleUppercase' => (bool)$collection->title_uppercase,
            'description' => $collection->description,
            'href' => $collection->href,
            'ctaLabel' => $collection->cta_label,
            'image' => $primaryImage ?? (
                $collection->image !== null
                    ? $collection->image->toApiImagePayload($collection->image->alt ?? $collection->title)
                    : MediaFile::emptyImagePayload($collection->title)
            ),
            'images' => $images,
            'imagePosition' => $collection->image_position ?? 'center',
        ];
    }
}
