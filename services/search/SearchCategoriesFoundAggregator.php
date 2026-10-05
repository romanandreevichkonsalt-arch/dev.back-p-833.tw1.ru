<?php

namespace app\services\search;

use app\models\CatalogSubcategory;
use app\services\cache\ApiResponseCache;
use app\services\catalog\CatalogUrlSlugResolver;

class SearchCategoriesFoundAggregator
{
    public const MAX_ITEMS = 10;

    public function __construct(
        private readonly CatalogUrlSlugResolver $slugResolver = new CatalogUrlSlugResolver(),
        private readonly SearchDocumentBuilder $documentBuilder = new SearchDocumentBuilder(),
    ) {
    }

    /**
     * @param list<array<string, mixed>> $matchedProducts
     *
     * @return list<array{id: string, label: string, href: string, matchCount: int, icon: string|null, products: list<array<string, mixed>>}>
     */
    public function aggregate(array $matchedProducts, int $limit = self::MAX_ITEMS): array
    {
        /** @var array<string, array{id: string, label: string, href: string, matchCount: int, icon: string|null, _products: list<array<string, mixed>>}> $groups */
        $groups = [];
        $iconMap = $this->getSubcategoryIconMap();

        foreach ($matchedProducts as $product) {
            $slug = trim((string)($product['subcategory'] ?? ''));
            if ($slug === '') {
                continue;
            }

            $label = trim((string)($product['subcategoryLabel'] ?? $product['type'] ?? $slug));
            $groupKey = $slug . ':' . trim((string)($product['collectionSlug'] ?? ''));
            if (!isset($groups[$groupKey])) {
                $groups[$groupKey] = [
                    'id' => $slug,
                    'label' => $label,
                    'href' => $this->buildHref($product),
                    'matchCount' => 0,
                    'icon' => $this->resolveIconFromMap($slug, $iconMap),
                    '_products' => [],
                ];
            }

            $groups[$groupKey]['matchCount']++;
            $groups[$groupKey]['_products'][] = $product;
        }

        $items = array_values($groups);
        usort(
            $items,
            static fn (array $left, array $right): int => $right['matchCount'] <=> $left['matchCount']
                ?: strcmp($left['label'], $right['label'])
        );

        $result = [];
        foreach (array_slice($items, 0, $limit) as $item) {
            $previewDocuments = $this->documentBuilder->pickCategoryPreviewDocuments($item['_products']);
            unset($item['_products']);
            $item['products'] = array_map(
                fn (array $document): array => $this->documentBuilder->toPublicProduct($document),
                $previewDocuments
            );
            $result[] = $item;
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $product
     */
    private function buildHref(array $product): string
    {
        $directionSlug = trim((string)($product['collectionSlug'] ?? ''));
        $categorySlug = trim((string)($product['categorySlug'] ?? ''));
        $subcategorySlug = trim((string)($product['subcategory'] ?? ''));

        if ($directionSlug !== '' && $categorySlug !== '' && $subcategorySlug !== '') {
            return '/catalog/' . rawurlencode($directionSlug)
                . '/' . rawurlencode($categorySlug)
                . '/' . rawurlencode($subcategorySlug);
        }

        if ($subcategorySlug !== '') {
            return '/catalog?subcategory=' . rawurlencode($subcategorySlug);
        }

        return '/catalog';
    }

    /**
     * @return array<string, string|null>
     */
    private function getSubcategoryIconMap(): array
    {
        $config = \Yii::$app->params['apiCache'] ?? [];
        $ttl = (int)($config['searchBootstrapTtl'] ?? $config['catalogProductsTtl'] ?? 300);

        return self::resolveApiResponseCache()->get(
            'search',
            'subcategory-icons',
            function (): array {
                $map = [];
                $subcategories = CatalogSubcategory::find()
                    ->where(['is_active' => true])
                    ->all();
                foreach ($subcategories as $subcategory) {
                    $icon = $this->slugResolver->getSubcategoryIcon($subcategory);
                    foreach (array_filter([
                        (string)$subcategory->slug,
                        (string)($subcategory->url_slug ?? ''),
                    ]) as $key) {
                        $map[$key] = $icon;
                    }
                }

                return $map;
            },
            $ttl,
        );
    }

    /**
     * @param array<string, string|null> $iconMap
     */
    private function resolveIconFromMap(string $publicSubcategorySlug, array $iconMap): ?string
    {
        if (isset($iconMap[$publicSubcategorySlug])) {
            return $iconMap[$publicSubcategorySlug];
        }

        return null;
    }

    private static function resolveApiResponseCache(): ApiResponseCache
    {
        if (\Yii::$container->has(ApiResponseCache::class)) {
            return \Yii::$container->get(ApiResponseCache::class);
        }

        return new ApiResponseCache();
    }
}
