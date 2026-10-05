<?php

namespace app\services\search;

use app\models\CatalogCollection;
use app\models\CatalogSubcategory;
use app\services\cache\ApiResponseCache;
use app\services\catalog\CatalogUrlSlugResolver;

class SearchCatalogVocabulary
{
    public function __construct(
        private readonly ApiResponseCache $cache = new ApiResponseCache(),
        private readonly CatalogUrlSlugResolver $slugResolver = new CatalogUrlSlugResolver(),
    ) {
    }

    /**
     * @return list<array{id: string, label: string, href: string}>
     */
    public function getSubcategories(): array
    {
        $config = \Yii::$app->params['apiCache'] ?? [];

        return $this->cache->get(
            'search',
            'vocabulary-subcategories',
            fn (): array => $this->buildSubcategories(),
            (int)($config['catalogMenuTtl'] ?? 900)
        );
    }

    /**
     * @return list<array{id: string, label: string, href: string}>
     */
    public function getCollections(): array
    {
        $config = \Yii::$app->params['apiCache'] ?? [];

        return $this->cache->get(
            'search',
            'vocabulary-collections',
            fn (): array => $this->buildCollections(),
            (int)($config['catalogMenuTtl'] ?? 900)
        );
    }

    /**
     * @return list<array{id: string, label: string, href: string}>
     */
    private function buildSubcategories(): array
    {
        $items = [];
        $subcategories = CatalogSubcategory::find()
            ->where(['is_active' => true])
            ->with(['category'])
            ->orderBy(['sort_order' => SORT_ASC, 'label' => SORT_ASC])
            ->all();

        foreach ($subcategories as $subcategory) {
            $slug = $this->slugResolver->getSubcategoryPublicSlug($subcategory);
            if ($slug === '') {
                continue;
            }

            $category = $subcategory->category;
            $href = $category !== null
                ? '/catalog/line-1/' . rawurlencode($this->slugResolver->getCategoryPublicSlug($category))
                    . '/' . rawurlencode($slug)
                : '/catalog?subcategory=' . rawurlencode($slug);

            $items[] = [
                'id' => $slug,
                'label' => (string)$subcategory->label,
                'href' => $href,
            ];
        }

        return $items;
    }

    /**
     * @return list<array{id: string, label: string, href: string}>
     */
    private function buildCollections(): array
    {
        $items = [];
        $collections = CatalogCollection::find()
            ->where(['is_active' => true])
            ->with('direction')
            ->orderBy(['sort_order' => SORT_ASC, 'name' => SORT_ASC])
            ->all();

        foreach ($collections as $collection) {
            $direction = $collection->direction;
            if ($direction === null) {
                continue;
            }

            $slug = $this->slugResolver->getDirectionPublicSlug($direction);
            $label = $direction->label;
            $href = $this->slugResolver->buildListingUrl($direction);

            $items[] = [
                'id' => $slug,
                'label' => $label,
                'href' => $href,
            ];
        }

        return $items;
    }
}
