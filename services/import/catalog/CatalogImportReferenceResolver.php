<?php

namespace app\services\import\catalog;

use app\helpers\SlugHelper;
use app\models\CatalogCategory;
use app\models\CatalogCollection;
use app\models\CatalogDirection;
use app\models\CatalogFabricCollection;
use app\models\CatalogPriceCategory;
use app\models\CatalogSubcategory;

class CatalogImportReferenceResolver
{
    /** @var array<string, int> */
    private array $priceCategoryCache = [];

    public function resolveDirection(string $label): CatalogDirection
    {
        $label = trim($label);
        $normalized = mb_strtolower($label, 'UTF-8');
        $slug = str_contains($normalized, 'линия') ? 'line-1' : 'a-plus';

        $direction = CatalogDirection::find()->where(['slug' => $slug])->one();
        if ($direction === null) {
            throw new \RuntimeException('Направление «' . $label . '» не найдено в справочнике.');
        }

        return $direction;
    }

    public function resolveFabricCollectionByName(string $name): ?CatalogFabricCollection
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        $slug = SlugHelper::slugify($name);
        $normalizedName = mb_strtolower($name, 'UTF-8');

        $collection = CatalogFabricCollection::find()
            ->where([
                'or',
                ['slug' => $slug],
                ['name' => $name],
                ['like', 'name', $name, false],
            ])
            ->one();

        if ($collection instanceof CatalogFabricCollection) {
            return $collection;
        }

        $collection = CatalogFabricCollection::find()
            ->where('LOWER([[name]]) = :normalizedName', [':normalizedName' => $normalizedName])
            ->one();

        if ($collection instanceof CatalogFabricCollection) {
            return $collection;
        }

        if ($slug === '') {
            return null;
        }

        return CatalogFabricCollection::find()
            ->where(['like', 'slug', $slug, false])
            ->one() ?: null;
    }

    public function resolveCollection(int $directionId, string $name): CatalogCollection
    {
        $name = trim($name);
        $baseSlug = SlugHelper::slugify($name) ?: 'collection';
        $directionSlug = $this->resolveDirectionSlug($directionId);

        $collection = CatalogCollection::find()
            ->where(['direction_id' => $directionId])
            ->andWhere([
                'or',
                ['name' => $name],
                ['slug' => $baseSlug],
                ['slug' => $baseSlug . '-' . $directionSlug],
            ])
            ->one();

        if ($collection === null) {
            $slug = $this->buildUniqueCollectionSlug($baseSlug, $directionSlug);
            $collection = new CatalogCollection([
                'direction_id' => $directionId,
                'name' => $name,
                'slug' => $slug,
                'label' => $name,
                'title' => $name,
                'href' => '/catalog/' . $slug,
                'is_active' => true,
                'sort_order' => 0,
            ]);
            if (!$collection->save()) {
                throw new \RuntimeException('Не удалось создать коллекцию «' . $name . '»: ' . implode(' ', $collection->getFirstErrors()));
            }
        } elseif (trim((string)$collection->name) === '') {
            $collection->name = $name;
            $collection->label = $name;
            $collection->title = $name;
            $collection->save(false);
        }

        return $collection;
    }

    private function resolveDirectionSlug(int $directionId): string
    {
        $direction = CatalogDirection::findOne($directionId);

        return $direction !== null && trim((string)$direction->slug) !== ''
            ? trim((string)$direction->slug)
            : 'dir-' . $directionId;
    }

    private function buildUniqueCollectionSlug(string $baseSlug, string $directionSlug): string
    {
        $candidates = [$baseSlug, $baseSlug . '-' . $directionSlug];
        foreach ($candidates as $slug) {
            if (!CatalogCollection::find()->where(['slug' => $slug])->exists()) {
                return $slug;
            }
        }

        $suffix = 2;
        while (CatalogCollection::find()->where(['slug' => $baseSlug . '-' . $directionSlug . '-' . $suffix])->exists()) {
            $suffix++;
        }

        return $baseSlug . '-' . $directionSlug . '-' . $suffix;
    }

    public function resolveCategory(string $label): CatalogCategory
    {
        $label = trim($label);
        if ($label === '') {
            throw new \InvalidArgumentException('Название категории не может быть пустым.');
        }

        $byLabel = CatalogCategory::find()
            ->where(['label' => $label, 'is_active' => true])
            ->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC])
            ->all();

        if (count($byLabel) === 1) {
            return $byLabel[0];
        }

        $slug = SlugHelper::slugify($label) ?: 'category';
        if ($byLabel !== []) {
            foreach ($byLabel as $category) {
                if ($category->slug === $slug) {
                    return $category;
                }
            }

            return $byLabel[0];
        }

        $category = CatalogCategory::find()->where(['slug' => $slug])->one();
        if ($category === null) {
            $maxSort = (int)CatalogCategory::find()->max('sort_order');
            $category = new CatalogCategory([
                'slug' => $slug,
                'label' => $label,
                'sort_order' => $maxSort + 1,
                'is_active' => true,
            ]);
            $category->save(false);
        }

        return $category;
    }

    public function resolveSubcategory(CatalogCategory $category, string $label): CatalogSubcategory
    {
        $label = trim($label);
        $slug = SlugHelper::slugify($label) ?: 'item';

        $subcategory = CatalogSubcategory::find()
            ->where(['category_id' => $category->id, 'slug' => $slug])
            ->one();

        if ($subcategory === null) {
            $maxSort = (int)CatalogSubcategory::find()
                ->where(['category_id' => $category->id])
                ->max('sort_order');
            $subcategory = new CatalogSubcategory([
                'category_id' => $category->id,
                'slug' => $slug,
                'label' => $label,
                'sort_order' => $maxSort + 1,
                'is_active' => true,
            ]);
            $subcategory->save(false);
        }

        return $subcategory;
    }

    public function ensurePriceCategoriesUpTo(int $maxNumber): void
    {
        $now = date('Y-m-d H:i:s');
        for ($number = 1; $number <= $maxNumber; $number++) {
            $existing = CatalogPriceCategory::find()->where(['number' => $number])->one();
            if ($existing !== null) {
                continue;
            }

            $category = new CatalogPriceCategory([
                'number' => $number,
                'label' => 'Категория ' . $number,
                'sort_order' => $number - 1,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $category->save(false);
        }
    }

    public function resolvePriceCategoryId(int $number): ?int
    {
        if (!isset($this->priceCategoryCache[$number])) {
            $category = CatalogPriceCategory::find()->where(['number' => $number])->one();
            $this->priceCategoryCache[$number] = $category !== null ? (int)$category->id : 0;
        }

        $id = $this->priceCategoryCache[$number];

        return $id > 0 ? $id : null;
    }
}
