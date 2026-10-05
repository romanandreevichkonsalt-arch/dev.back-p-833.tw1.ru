<?php

namespace app\services\catalog;

use app\models\CatalogCategory;
use app\models\CatalogDirection;
use app\models\CatalogProduct;
use app\models\CatalogSubcategory;

final class CatalogTaxonomyService
{
    public function __construct(
        private readonly CatalogUrlSlugResolver $slugResolver = new CatalogUrlSlugResolver(),
    ) {
    }

    /**
     * @return list<array{slug: string, label: string, subcategories: list<array{slug: string, label: string, count: int}>}>
     */
    public function buildCategoriesForDirection(?CatalogDirection $direction): array
    {
        if ($direction === null) {
            return [];
        }

        $categories = CatalogCategory::find()
            ->where(['is_active' => true])
            ->with(['subcategories'])
            ->orderBy(['sort_order' => SORT_ASC, 'label' => SORT_ASC])
            ->all();
        $result = [];

        foreach ($categories as $category) {
            $subcategoriesPayload = [];
            foreach ($category->subcategories as $subcategory) {
                if (!$subcategory->is_active) {
                    continue;
                }

                $count = $this->countProducts($direction, $category, $subcategory);
                if ($count <= 0) {
                    continue;
                }

                $subcategoriesPayload[] = [
                    'slug' => $this->slugResolver->getSubcategoryPublicSlug($subcategory),
                    'label' => (string)$subcategory->label,
                    'count' => $count,
                ];
            }

            if ($subcategoriesPayload === []) {
                continue;
            }

            $result[] = [
                'slug' => $this->slugResolver->getCategoryPublicSlug($category),
                'label' => (string)$category->label,
                'subcategories' => $subcategoriesPayload,
            ];
        }

        return $result;
    }

    private function countProducts(
        CatalogDirection $direction,
        CatalogCategory $category,
        CatalogSubcategory $subcategory
    ): int {
        return (int)CatalogProduct::find()
            ->alias('p')
            ->innerJoin('{{%catalog_collections}} c', 'c.id = p.collection_id')
            ->where([
                'p.is_active' => true,
                'p.subcategory_id' => $subcategory->id,
                'c.direction_id' => $direction->id,
                'c.is_active' => true,
            ])
            ->count();
    }
}
