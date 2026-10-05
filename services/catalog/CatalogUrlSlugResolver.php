<?php

namespace app\services\catalog;

use app\models\CatalogCategory;
use app\models\CatalogCollection;
use app\models\CatalogDirection;
use app\models\CatalogSubcategory;

final class CatalogUrlSlugResolver
{
    /** @var array<string, string> */
    private const SUBCATEGORY_ICONS = [
        'pryamye' => 'search-sofa-straight',
        'straight' => 'search-sofa-straight',
        'uglovye' => 'search-sofa-corner',
        'corner' => 'search-sofa-corner',
        'modulnye' => 'search-sofa-modular',
        'modular' => 'search-sofa-modular',
        'kompaktnye' => 'search-sofa',
        'compact' => 'search-sofa',
        'kreslo' => 'search-armchair',
        'armchair' => 'search-armchair',
        'kreslo-krovat' => 'search-armchair',
        'chair-bed' => 'search-armchair',
    ];

    /** @var array<string, string> */
    private const CATEGORY_ICONS = [
        'divany' => 'search-sofa',
        'sofa' => 'search-sofa',
        'kresla' => 'search-armchair',
        'armchair' => 'search-armchair',
        'kombinatsii' => 'search-combinations',
        'combination' => 'search-combinations',
    ];

    public function findDirectionByCollectionParam(string $slug): ?CatalogDirection
    {
        $slug = trim($slug);
        if ($slug === '') {
            return null;
        }

        return CatalogDirection::find()
            ->where(['slug' => $slug, 'is_active' => true])
            ->one();
    }

    public function findModelLineBySlug(string $slug): ?CatalogCollection
    {
        $slug = trim($slug);
        if ($slug === '') {
            return null;
        }

        return CatalogCollection::find()
            ->where(['slug' => $slug, 'is_active' => true])
            ->with('direction')
            ->one();
    }

    public function findCategoryBySlug(string $slug): ?CatalogCategory
    {
        $slug = trim($slug);
        if ($slug === '') {
            return null;
        }

        return CatalogCategory::find()
            ->where(['is_active' => true])
            ->andWhere(['or', ['slug' => $slug], ['url_slug' => $slug]])
            ->one();
    }

    public function findSubcategoryBySlug(string $slug, ?int $categoryId = null): ?CatalogSubcategory
    {
        $slug = trim($slug);
        if ($slug === '') {
            return null;
        }

        $query = CatalogSubcategory::find()
            ->where(['is_active' => true])
            ->andWhere(['or', ['slug' => $slug], ['url_slug' => $slug]])
            ->with('category');

        if ($categoryId !== null) {
            $query->andWhere(['category_id' => $categoryId]);
        }

        $matches = $query->all();
        if ($matches === []) {
            return null;
        }

        return $matches[0];
    }

    public function getCategoryPublicSlug(CatalogCategory $category): string
    {
        $urlSlug = trim((string)$category->url_slug);

        return $urlSlug !== '' ? $urlSlug : (string)$category->slug;
    }

    public function getSubcategoryPublicSlug(CatalogSubcategory $subcategory): string
    {
        $urlSlug = trim((string)$subcategory->url_slug);

        return $urlSlug !== '' ? $urlSlug : (string)$subcategory->slug;
    }

    public function getDirectionPublicSlug(CatalogDirection $direction): string
    {
        return (string)$direction->slug;
    }

    public function buildProductUrl(string $productSlug): string
    {
        return '/product/' . rawurlencode($productSlug);
    }

    public function buildListingUrl(
        ?CatalogDirection $direction,
        ?CatalogCategory $category = null,
        ?CatalogSubcategory $subcategory = null
    ): string {
        if ($direction === null) {
            return '/catalog';
        }

        $parts = ['/catalog', $this->getDirectionPublicSlug($direction)];
        if ($category !== null) {
            $parts[] = $this->getCategoryPublicSlug($category);
        }
        if ($subcategory !== null) {
            $parts[] = $this->getSubcategoryPublicSlug($subcategory);
        }

        return implode('/', $parts);
    }

    public function getSubcategoryIcon(?CatalogSubcategory $subcategory): ?string
    {
        if ($subcategory === null) {
            return null;
        }

        $publicSlug = $this->getSubcategoryPublicSlug($subcategory);

        return self::SUBCATEGORY_ICONS[$publicSlug]
            ?? self::SUBCATEGORY_ICONS[(string)$subcategory->slug]
            ?? null;
    }

    public function getCategoryIcon(?CatalogCategory $category): ?string
    {
        if ($category === null) {
            return null;
        }

        $publicSlug = $this->getCategoryPublicSlug($category);

        return self::CATEGORY_ICONS[$publicSlug]
            ?? self::CATEGORY_ICONS[(string)$category->slug]
            ?? null;
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function normalizeRequestParams(array $params): array
    {
        $normalized = $params;

        $modelLine = trim((string)($params['modelLine'] ?? ''));
        $collectionRaw = $params['collection'] ?? null;
        $collectionGroups = CatalogRequestParams::parseBooleanParam($collectionRaw);
        if ($collectionGroups !== null) {
            $normalized['collectionGroups'] = $collectionGroups;
            unset($normalized['collection']);
        }

        $direction = trim((string)($params['direction'] ?? ''));

        if ($modelLine !== '') {
            $normalized['modelLine'] = $modelLine;
        } elseif ($collectionGroups === null) {
            $collection = trim((string)($collectionRaw ?? ''));
            if ($collection !== '' && $this->findDirectionByCollectionParam($collection) !== null) {
                $normalized['direction'] = $collection;
                unset($normalized['collection']);
            } elseif ($direction !== '') {
                $normalized['direction'] = $direction;
            } elseif ($collection !== '') {
                $normalized['modelLine'] = $collection;
                unset($normalized['collection']);
            }
        } elseif ($direction !== '') {
            $normalized['direction'] = $direction;
        }

        $sort = trim((string)($params['sort'] ?? ''));
        if ($sort !== '') {
            $normalized['sort'] = str_replace('-', '_', $sort);
        }

        return $normalized;
    }
}
