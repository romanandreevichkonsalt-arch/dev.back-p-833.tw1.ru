<?php

namespace app\services\catalog;

use app\models\User;

class CatalogUnifiedService
{
    private CatalogService $catalog;
    private CatalogNavigationService $navigation;
    private CatalogProductListingService $listing;
    private CatalogTaxonomyService $taxonomy;
    private CatalogRequestParams $requestParams;

    public function __construct(
        ?CatalogService $catalog = null,
        ?CatalogNavigationService $navigation = null,
        ?CatalogProductListingService $listing = null,
        ?CatalogTaxonomyService $taxonomy = null,
        ?CatalogRequestParams $requestParams = null,
    ) {
        $this->catalog = $catalog ?? \Yii::$container->get(CatalogService::class);
        $this->navigation = $navigation ?? \Yii::$container->get(CatalogNavigationService::class);
        $this->listing = $listing ?? \Yii::$container->get(CatalogProductListingService::class);
        $this->taxonomy = $taxonomy ?? \Yii::$container->get(CatalogTaxonomyService::class);
        $this->requestParams = $requestParams ?? \Yii::$container->get(CatalogRequestParams::class);
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function get(array $params, ?User $dealer = null, bool $alwaysIncludeListing = false): array
    {
        $normalized = $this->requestParams->normalize($params);
        $menu = $this->catalog->getMenu();
        $navigation = $this->navigation->getNavigation($normalized);

        $direction = null;
        $collectionSlug = trim((string)($normalized['direction'] ?? ''));
        if ($collectionSlug !== '') {
            $direction = (new CatalogUrlSlugResolver())->findDirectionByCollectionParam($collectionSlug);
        }

        $response = array_merge($menu, [
            'breadcrumb' => $navigation['breadcrumb'],
            'level' => $navigation['level'],
            'navigationItems' => $navigation['items'],
            'categories' => $this->taxonomy->buildCategoriesForDirection($direction),
        ]);

        if (isset($navigation['meta'])) {
            $response['navigationMeta'] = $navigation['meta'];
        }

        if ($this->shouldIncludeListing($normalized, $alwaysIncludeListing)) {
            $listing = $this->listing->getProducts($normalized, $dealer);
            $response['items'] = $listing['items'];
            $response['filters'] = $listing['filters'];
            $response['meta'] = $listing['meta'];
            $response['breadcrumb'] = $listing['breadcrumb'];
            if ($direction !== null) {
                $response['categories'] = $this->taxonomy->buildCategoriesForDirection($direction);
            }
        } else {
            $response['items'] = [];
            $response['filters'] = [
                'price' => null,
                'colors' => [],
                'textures' => [],
                'dimensions' => null,
            ];
            $response['meta'] = null;
        }

        return $response;
    }

    /**
     * Meta листинга (total и пагинация) без полного ответа menu.
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function getListingMeta(array $params, bool $alwaysIncludeListing = false, ?User $dealer = null): array
    {
        $normalized = $this->requestParams->normalize($params);

        if (!$this->shouldIncludeListing($normalized, $alwaysIncludeListing)) {
            return [
                'total' => 0,
                'page' => max(1, (int)($normalized['page'] ?? 1)),
                'perPage' => min(100, max(1, (int)($normalized['perPage'] ?? 24))),
                'sort' => trim((string)($normalized['sort'] ?? '')) !== ''
                    ? str_replace('-', '_', (string)$normalized['sort'])
                    : 'default',
                'scopeMode' => 'shortcut',
                'applied' => [],
            ];
        }

        return $this->listing->countProducts($normalized, $dealer);
    }

    /**
     * @param array<string, mixed> $params
     */
    private function shouldIncludeListing(array $params, bool $alwaysIncludeListing = false): bool
    {
        if ($alwaysIncludeListing) {
            return true;
        }

        if (CatalogRequestParams::isCollectionGroupsEnabled($params)) {
            return true;
        }

        if ($this->hasScopeParam($params)) {
            return true;
        }

        foreach (['priceMin', 'priceMax', 'widthMin', 'widthMax', 'heightMin', 'heightMax', 'depthMin', 'depthMax'] as $key) {
            if (($params[$key] ?? '') !== '' && $params[$key] !== null) {
                return true;
            }
        }

        if ($this->parseTruthyParam($params['sleepingPlace'] ?? null)) {
            return true;
        }

        if (($params['sort'] ?? '') !== '' && ($params['sort'] ?? 'default') !== 'default') {
            return true;
        }

        if ((int)($params['page'] ?? 1) > 1) {
            return true;
        }

        if ((int)($params['perPage'] ?? 24) !== 24) {
            return true;
        }

        $colors = $params['color'] ?? [];
        if (!is_array($colors)) {
            $colors = $colors !== '' && $colors !== null ? [(string)$colors] : [];
        }
        if ($colors !== []) {
            return true;
        }

        $textures = $params['texture'] ?? [];
        if (!is_array($textures)) {
            $textures = $textures !== '' && $textures !== null ? [(string)$textures] : [];
        }

        return $textures !== [];
    }

    /**
     * @param array<string, mixed> $params
     */
    private function hasScopeParam(array $params): bool
    {
        foreach (['direction', 'collection', 'modelLine', 'category', 'subcategory'] as $key) {
            if (trim((string)($params[$key] ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }

    private function parseTruthyParam(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        $normalized = mb_strtolower(trim((string)$value), 'UTF-8');

        return in_array($normalized, ['1', 'true', 'yes', 'да'], true);
    }
}
