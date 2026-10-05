<?php

namespace app\services\catalog;

use app\models\CatalogCategory;
use app\models\CatalogCollection;
use app\models\CatalogDirection;
use app\models\CatalogProduct;
use app\models\CatalogSubcategory;
use app\services\cache\ApiResponseCache;
use yii\db\Expression;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;

class CatalogNavigationService
{
    private ApiResponseCache $cache;

    public function __construct(?ApiResponseCache $cache = null)
    {
        $this->cache = $cache ?? \Yii::$container->get(ApiResponseCache::class);
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function getNavigation(array $params): array
    {
        $params = (new CatalogUrlSlugResolver())->normalizeRequestParams($params);
        $directionSlug = trim((string)($params['direction'] ?? ''));
        $modelLineSlug = trim((string)($params['modelLine'] ?? ''));
        $categorySlug = trim((string)($params['category'] ?? ''));
        $subcategorySlug = trim((string)($params['subcategory'] ?? ''));

        $cacheKey = implode(':', array_filter([
            $directionSlug ?: '-',
            $modelLineSlug ?: '-',
            $categorySlug ?: '-',
            $subcategorySlug ?: '-',
        ]));

        $config = \Yii::$app->params['apiCache'] ?? [];

        return $this->cache->get(
            'catalog',
            'navigation:' . $cacheKey,
            fn (): array => $this->buildNavigation($params),
            (int)($config['catalogMenuTtl'] ?? 900)
        );
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function buildNavigation(array $params): array
    {
        $params = (new CatalogUrlSlugResolver())->normalizeRequestParams($params);
        $directionSlug = trim((string)($params['direction'] ?? ''));
        $modelLineSlug = trim((string)($params['modelLine'] ?? ''));
        $categorySlug = trim((string)($params['category'] ?? ''));
        $subcategorySlug = trim((string)($params['subcategory'] ?? ''));

        if ($directionSlug === '' && $modelLineSlug === '' && $categorySlug === '' && $subcategorySlug === '') {
            return $this->buildRootNavigation();
        }

        if ($subcategorySlug !== '' && $directionSlug === '' && $modelLineSlug === '' && $categorySlug === '') {
            return $this->buildSubcategoryShortcutNavigation($subcategorySlug);
        }

        $scope = CatalogScope::fromParams($params);

        if ($scope->subcategory !== null) {
            return [
                'breadcrumb' => $this->buildBreadcrumb($scope),
                'level' => 'subcategory',
                'items' => [],
                'meta' => $this->buildSubcategoryMeta($scope),
            ];
        }

        if ($scope->category !== null) {
            return [
                'breadcrumb' => $this->buildBreadcrumb($scope),
                'level' => 'subcategory',
                'items' => $this->buildSubcategoryItems($scope),
            ];
        }

        if ($scope->collection !== null) {
            return [
                'breadcrumb' => $this->buildBreadcrumb($scope),
                'level' => 'category',
                'items' => $this->buildCategoryItems($scope),
            ];
        }

        if ($scope->direction !== null) {
            return [
                'breadcrumb' => $this->buildBreadcrumb($scope),
                'level' => 'collection',
                'items' => $this->buildCollectionItems($scope->direction),
            ];
        }

        throw new BadRequestHttpException('Некорректные параметры навигации.');
    }

    /**
     * @return array<string, mixed>
     */
    private function buildRootNavigation(): array
    {
        $directions = CatalogDirection::find()
            ->where(['is_active' => true])
            ->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC])
            ->all();

        return [
            'breadcrumb' => [],
            'level' => 'direction',
            'items' => array_map(static fn (CatalogDirection $direction): array => [
                'id' => $direction->slug,
                'label' => $direction->label,
            ], $directions),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSubcategoryShortcutNavigation(string $subcategorySlug): array
    {
        $matches = CatalogSubcategory::find()
            ->where(['is_active' => true])
            ->andWhere(['or', ['slug' => $subcategorySlug], ['url_slug' => $subcategorySlug]])
            ->with('category')
            ->all();

        if ($matches === []) {
            throw new NotFoundHttpException('Подкатегория не найдена.');
        }
        if (count($matches) > 1) {
            throw new BadRequestHttpException('Slug подкатегории неоднозначен. Укажите category.');
        }

        $scope = new CatalogScope();
        $scope->subcategory = $matches[0];
        $scope->category = $matches[0]->category;
        $scope->scopeMode = 'shortcut';

        return [
            'breadcrumb' => $this->buildBreadcrumb($scope),
            'level' => 'subcategory',
            'items' => [],
            'meta' => $this->buildSubcategoryMeta($scope),
        ];
    }

    /**
     * @return list<array{id: string, label: string}>
     */
    private function buildCollectionItems(CatalogDirection $direction): array
    {
        $collections = CatalogCollection::find()
            ->where(['direction_id' => $direction->id, 'is_active' => true])
            ->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC])
            ->all();

        return array_map(static fn (CatalogCollection $collection): array => [
            'id' => $collection->slug,
            'label' => $collection->getDisplayName(),
        ], $collections);
    }

    /**
     * @return list<array{id: string, label: string, count: int}>
     */
    private function buildCategoryItems(CatalogScope $scope): array
    {
        $productQuery = CatalogProduct::find()
            ->alias('p')
            ->select(['sc.category_id', 'count' => new Expression('COUNT(DISTINCT p.id)')])
            ->innerJoin('{{%catalog_subcategories}} sc', 'sc.id = p.subcategory_id')
            ->where(['p.is_active' => true])
            ->groupBy(['sc.category_id']);

        if ($scope->direction !== null || $scope->collection !== null) {
            $productQuery->innerJoin('{{%catalog_collections}} c', 'c.id = p.collection_id');
        }
        if ($scope->collection !== null) {
            $productQuery->andWhere(['p.collection_id' => $scope->collection->id]);
        }
        if ($scope->direction !== null) {
            $productQuery->andWhere(['c.direction_id' => $scope->direction->id]);
        }

        $countsByCategory = $productQuery->indexBy('category_id')->asArray()->all();

        $categories = CatalogCategory::find()
            ->where(['is_active' => true])
            ->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC])
            ->all();
        $items = [];
        foreach ($categories as $category) {
            $count = (int)($countsByCategory[$category->id]['count'] ?? 0);
            if ($count === 0) {
                continue;
            }
            $items[] = [
                'id' => $category->slug,
                'label' => $category->label,
                'count' => $count,
            ];
        }

        return $items;
    }

    /**
     * @return list<array{id: string, label: string, count: int}>
     */
    private function buildSubcategoryItems(CatalogScope $scope): array
    {
        if ($scope->category === null) {
            return [];
        }

        $productQuery = CatalogProduct::find()
            ->alias('p')
            ->select(['p.subcategory_id', 'count' => new Expression('COUNT(DISTINCT p.id)')])
            ->innerJoin('{{%catalog_subcategories}} sc', 'sc.id = p.subcategory_id')
            ->where(['p.is_active' => true])
            ->groupBy(['p.subcategory_id']);

        if ($scope->direction !== null || $scope->collection !== null) {
            $productQuery->innerJoin('{{%catalog_collections}} c', 'c.id = p.collection_id');
        }
        if ($scope->collection !== null) {
            $productQuery->andWhere(['p.collection_id' => $scope->collection->id]);
        }
        if ($scope->direction !== null) {
            $productQuery->andWhere(['c.direction_id' => $scope->direction->id]);
        }
        $productQuery->andWhere(['sc.category_id' => $scope->category->id]);

        $countsBySubcategory = $productQuery->indexBy('subcategory_id')->asArray()->all();

        $subcategories = CatalogSubcategory::find()
            ->where(['category_id' => $scope->category->id, 'is_active' => true])
            ->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC])
            ->all();

        $items = [];
        foreach ($subcategories as $subcategory) {
            $count = (int)($countsBySubcategory[$subcategory->id]['count'] ?? 0);
            if ($count === 0) {
                continue;
            }
            $items[] = [
                'id' => $subcategory->slug,
                'label' => $subcategory->label,
                'count' => $count,
            ];
        }

        return $items;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSubcategoryMeta(CatalogScope $scope): array
    {
        if ($scope->subcategory === null) {
            return [];
        }

        $rows = CatalogProduct::find()
            ->alias('p')
            ->select(['c.slug', 'c.name', 'c.label', 'count' => new Expression('COUNT(DISTINCT p.id)')])
            ->innerJoin('{{%catalog_collections}} c', 'c.id = p.collection_id')
            ->where(['p.is_active' => true, 'p.subcategory_id' => $scope->subcategory->id])
            ->groupBy(['c.id'])
            ->orderBy(['c.sort_order' => SORT_ASC, 'c.id' => SORT_ASC])
            ->asArray()
            ->all();

        return [
            'availableCollections' => array_map(static fn (array $row): array => [
                'id' => $row['slug'],
                'label' => trim((string)($row['label'] ?: $row['name'])),
                'count' => (int)$row['count'],
            ], $rows),
        ];
    }

    /**
     * @return list<array{level: string, id: string, label: string}>
     */
    public function buildBreadcrumb(CatalogScope $scope): array
    {
        $breadcrumb = [];
        if ($scope->direction !== null) {
            $breadcrumb[] = [
                'level' => 'direction',
                'id' => $scope->direction->slug,
                'label' => $scope->direction->label,
            ];
        } elseif ($scope->collection?->direction !== null) {
            $breadcrumb[] = [
                'level' => 'direction',
                'id' => $scope->collection->direction->slug,
                'label' => $scope->collection->direction->label,
            ];
        }

        if ($scope->collection !== null) {
            $breadcrumb[] = [
                'level' => 'collection',
                'id' => $scope->collection->slug,
                'label' => $scope->collection->getDisplayName(),
            ];
        }

        if ($scope->category !== null) {
            $breadcrumb[] = [
                'level' => 'category',
                'id' => $scope->category->slug,
                'label' => $scope->category->label,
            ];
        }

        if ($scope->subcategory !== null) {
            $breadcrumb[] = [
                'level' => 'subcategory',
                'id' => $scope->subcategory->slug,
                'label' => $scope->subcategory->label,
            ];
        }

        return $breadcrumb;
    }
}
