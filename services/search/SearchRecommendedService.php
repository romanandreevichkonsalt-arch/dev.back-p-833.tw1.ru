<?php

namespace app\services\search;

use app\models\CatalogCategory;
use app\models\CatalogDirection;
use app\models\CatalogProduct;
use app\models\CatalogSubcategory;
use app\models\SearchRecommendedProduct;
use app\modules\admin\helpers\HomePageProductsHelper;

class SearchRecommendedService
{
    /**
     * @return array<string, mixed>
     */
    public function buildAdminFormData(): array
    {
        $rows = SearchRecommendedProduct::find()
            ->with([
                'catalogProduct.collection',
                'catalogProduct.fabricColor.fabricCollection',
                'catalogProduct.catalogModel.collection',
            ])
            ->all();

        $byKey = [];
        foreach ($rows as $row) {
            $byKey[$this->rowKey($row->scope_type, (int)$row->scope_id, (int)$row->slot)] = $row;
        }

        $mainSlots = $this->buildSlots(
            SearchRecommendedProduct::SCOPE_MAIN,
            0,
            SearchRecommendedProduct::MAIN_SLOT_COUNT,
            $byKey
        );

        $directions = [];
        foreach ($this->findActiveDirections() as $direction) {
            $directions[] = [
                'id' => (int)$direction->id,
                'label' => $direction->label,
                'slug' => $direction->slug,
                'slots' => $this->buildSlots(
                    SearchRecommendedProduct::SCOPE_DIRECTION,
                    (int)$direction->id,
                    SearchRecommendedProduct::SCOPED_SLOT_COUNT,
                    $byKey
                ),
            ];
        }

        $categories = [];
        foreach ($this->findActiveCategories() as $category) {
            $categories[] = [
                'id' => (int)$category->id,
                'label' => $category->label,
                'slug' => $category->url_slug ?: $category->slug,
                'slots' => $this->buildSlots(
                    SearchRecommendedProduct::SCOPE_CATEGORY,
                    (int)$category->id,
                    SearchRecommendedProduct::SCOPED_SLOT_COUNT,
                    $byKey
                ),
            ];
        }

        $subcategoryGroups = [];
        foreach ($this->findActiveCategories() as $category) {
            $subcategories = [];
            foreach ($this->findActiveSubcategoriesForCategory((int)$category->id) as $subcategory) {
                $subcategories[] = [
                    'id' => (int)$subcategory->id,
                    'label' => $subcategory->label,
                    'slug' => $subcategory->url_slug ?: $subcategory->slug,
                    'slots' => $this->buildSlots(
                        SearchRecommendedProduct::SCOPE_SUBCATEGORY,
                        (int)$subcategory->id,
                        SearchRecommendedProduct::SCOPED_SLOT_COUNT,
                        $byKey
                    ),
                ];
            }

            if ($subcategories === []) {
                continue;
            }

            $subcategoryGroups[] = [
                'id' => (int)$category->id,
                'label' => $category->label,
                'subcategories' => $subcategories,
            ];
        }

        return [
            'main' => $mainSlots,
            'directions' => $directions,
            'categories' => $categories,
            'subcategoryGroups' => $subcategoryGroups,
        ];
    }

    /**
     * @return list<string>
     */
    public function saveFromPost(array $post): array
    {
        $recommended = $post['recommended'] ?? [];
        if (!is_array($recommended)) {
            return ['Некорректные данные формы.'];
        }

        $errors = [];
        $desiredRows = [];

        $this->collectDesiredRows(
            $desiredRows,
            $errors,
            SearchRecommendedProduct::SCOPE_MAIN,
            0,
            SearchRecommendedProduct::MAIN_SLOT_COUNT,
            $recommended['main'] ?? []
        );

        foreach ($this->findActiveDirections() as $direction) {
            $directionId = (int)$direction->id;
            $slots = $recommended['direction'][$directionId] ?? $recommended['direction'][(string)$directionId] ?? [];
            $this->collectDesiredRows(
                $desiredRows,
                $errors,
                SearchRecommendedProduct::SCOPE_DIRECTION,
                $directionId,
                SearchRecommendedProduct::SCOPED_SLOT_COUNT,
                is_array($slots) ? $slots : [],
                $direction->label
            );
        }

        foreach ($this->findActiveCategories() as $category) {
            $categoryId = (int)$category->id;
            $slots = $recommended['category'][$categoryId] ?? $recommended['category'][(string)$categoryId] ?? [];
            $this->collectDesiredRows(
                $desiredRows,
                $errors,
                SearchRecommendedProduct::SCOPE_CATEGORY,
                $categoryId,
                SearchRecommendedProduct::SCOPED_SLOT_COUNT,
                is_array($slots) ? $slots : [],
                $category->label
            );
        }

        foreach ($this->findActiveCategories() as $category) {
            foreach ($this->findActiveSubcategoriesForCategory((int)$category->id) as $subcategory) {
                $subcategoryId = (int)$subcategory->id;
                $slots = $recommended['subcategory'][$subcategoryId] ?? $recommended['subcategory'][(string)$subcategoryId] ?? [];
                $this->collectDesiredRows(
                    $desiredRows,
                    $errors,
                    SearchRecommendedProduct::SCOPE_SUBCATEGORY,
                    $subcategoryId,
                    SearchRecommendedProduct::SCOPED_SLOT_COUNT,
                    is_array($slots) ? $slots : [],
                    $category->label . ' → ' . $subcategory->label
                );
            }
        }

        if ($errors !== []) {
            return $errors;
        }

        $transaction = \Yii::$app->db->beginTransaction();
        try {
            SearchRecommendedProduct::deleteAll([]);
            foreach ($desiredRows as $row) {
                $model = new SearchRecommendedProduct([
                    'scope_type' => $row['scope_type'],
                    'scope_id' => $row['scope_id'],
                    'slot' => $row['slot'],
                    'catalog_product_id' => $row['catalog_product_id'],
                ]);
                if (!$model->save(false)) {
                    throw new \RuntimeException('Не удалось сохранить рекомендацию.');
                }
            }
            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();

            return ['Не удалось сохранить рекомендации.'];
        }

        return [];
    }

    /**
     * @return array{recommended: list<array<string, mixed>>, recommendedGroups: array<string, mixed>}
     */
    public function buildBootstrapPayload(): array
    {
        $rows = SearchRecommendedProduct::find()
            ->with($this->productEagerLoad())
            ->orderBy(['scope_type' => SORT_ASC, 'scope_id' => SORT_ASC, 'slot' => SORT_ASC, 'id' => SORT_ASC])
            ->all();

        $groups = [
            'main' => [],
            'directions' => [],
            'categories' => [],
            'subcategories' => [],
        ];

        $directionOrder = [];
        foreach ($this->findActiveDirections() as $direction) {
            $directionOrder[(int)$direction->id] = $direction->slug;
        }

        $categoryOrder = [];
        foreach ($this->findActiveCategories() as $category) {
            $categoryOrder[(int)$category->id] = $category->url_slug ?: $category->slug;
        }

        $subcategoryOrder = [];
        foreach ($this->findActiveCategories() as $category) {
            foreach ($this->findActiveSubcategoriesForCategory((int)$category->id) as $subcategory) {
                $subcategoryOrder[(int)$subcategory->id] = $subcategory->url_slug ?: $subcategory->slug;
            }
        }

        $rowsByScope = [
            SearchRecommendedProduct::SCOPE_MAIN => [],
            SearchRecommendedProduct::SCOPE_DIRECTION => [],
            SearchRecommendedProduct::SCOPE_CATEGORY => [],
            SearchRecommendedProduct::SCOPE_SUBCATEGORY => [],
        ];

        foreach ($rows as $row) {
            $product = $row->catalogProduct;
            if ($product === null || !$this->isProductEligible($product)) {
                continue;
            }

            $item = $product->toSearchApiItem();
            $scopeId = (int)$row->scope_id;
            $slot = (int)$row->slot;

            $rowsByScope[$row->scope_type][] = [
                'scope_id' => $scopeId,
                'slot' => $slot,
                'item' => $item,
            ];

            match ($row->scope_type) {
                SearchRecommendedProduct::SCOPE_MAIN => $groups['main'][$slot] = $item,
                SearchRecommendedProduct::SCOPE_DIRECTION => $this->assignGroupedItem(
                    $groups['directions'],
                    $directionOrder[$scopeId] ?? null,
                    $slot,
                    $item
                ),
                SearchRecommendedProduct::SCOPE_CATEGORY => $this->assignGroupedItem(
                    $groups['categories'],
                    $categoryOrder[$scopeId] ?? null,
                    $slot,
                    $item
                ),
                SearchRecommendedProduct::SCOPE_SUBCATEGORY => $this->assignGroupedItem(
                    $groups['subcategories'],
                    $subcategoryOrder[$scopeId] ?? null,
                    $slot,
                    $item
                ),
                default => null,
            };
        }

        $groups['main'] = $this->normalizeSlotList($groups['main'], SearchRecommendedProduct::MAIN_SLOT_COUNT);
        $groups['directions'] = $this->normalizeGroupedLists($groups['directions'], SearchRecommendedProduct::SCOPED_SLOT_COUNT);
        $groups['categories'] = $this->normalizeGroupedLists($groups['categories'], SearchRecommendedProduct::SCOPED_SLOT_COUNT);
        $groups['subcategories'] = $this->normalizeGroupedLists($groups['subcategories'], SearchRecommendedProduct::SCOPED_SLOT_COUNT);

        $recommended = [];
        foreach ($this->flattenScopeRows($rowsByScope[SearchRecommendedProduct::SCOPE_MAIN]) as $entry) {
            $recommended[] = $entry['item'];
        }
        foreach ($this->flattenScopeRows($rowsByScope[SearchRecommendedProduct::SCOPE_DIRECTION], $directionOrder) as $entry) {
            $recommended[] = $entry['item'];
        }
        foreach ($this->flattenScopeRows($rowsByScope[SearchRecommendedProduct::SCOPE_CATEGORY], $categoryOrder) as $entry) {
            $recommended[] = $entry['item'];
        }
        foreach ($this->flattenScopeRows($rowsByScope[SearchRecommendedProduct::SCOPE_SUBCATEGORY], $subcategoryOrder) as $entry) {
            $recommended[] = $entry['item'];
        }

        return [
            'recommended' => $recommended,
            'recommendedGroups' => $groups,
        ];
    }

    public function hasConfiguredProducts(): bool
    {
        return SearchRecommendedProduct::find()->exists();
    }

    /**
     * @param array<string, SearchRecommendedProduct> $byKey
     * @return list<array{catalog_product_id: int|string, product_search: string}>
     */
    private function buildSlots(string $scopeType, int $scopeId, int $slotCount, array $byKey): array
    {
        $slots = [];
        for ($slot = 0; $slot < $slotCount; $slot++) {
            $row = $byKey[$this->rowKey($scopeType, $scopeId, $slot)] ?? null;
            $productId = $row?->catalog_product_id ?? '';
            $label = '';
            if ($row !== null && $row->catalogProduct !== null) {
                $pickerItem = HomePageProductsHelper::pickerItemFromProduct($row->catalogProduct);
                $label = (string)($pickerItem['productTitle'] ?: $pickerItem['title']);
            }

            $slots[] = [
                'catalog_product_id' => $productId,
                'product_search' => $label,
            ];
        }

        return $slots;
    }

    /**
     * @param list<array<string, mixed>> $desiredRows
     * @param list<string> $errors
     * @param array<int|string, mixed> $slotsPost
     */
    private function collectDesiredRows(
        array &$desiredRows,
        array &$errors,
        string $scopeType,
        int $scopeId,
        int $slotCount,
        array $slotsPost,
        ?string $scopeLabel = null,
    ): void {
        for ($slot = 0; $slot < $slotCount; $slot++) {
            $slotPost = $slotsPost[$slot] ?? $slotsPost[(string)$slot] ?? [];
            if (!is_array($slotPost)) {
                continue;
            }

            $productId = (int)($slotPost['catalog_product_id'] ?? 0);
            if ($productId <= 0) {
                continue;
            }

            $product = CatalogProduct::find()
                ->where(['id' => $productId])
                ->with(['collection', 'subcategory'])
                ->one();

            if ($product === null) {
                $errors[] = $this->formatScopeError($scopeType, $scopeLabel, $slot, 'товар не найден');
                continue;
            }

            if (!$this->isProductEligible($product)) {
                $errors[] = $this->formatScopeError($scopeType, $scopeLabel, $slot, 'товар неактивен или кастомный');
                continue;
            }

            if (!$this->matchesScope($product, $scopeType, $scopeId)) {
                $errors[] = $this->formatScopeError($scopeType, $scopeLabel, $slot, 'товар не подходит для этого раздела');
                continue;
            }

            $desiredRows[] = [
                'scope_type' => $scopeType,
                'scope_id' => $scopeId,
                'slot' => $slot,
                'catalog_product_id' => $productId,
            ];
        }
    }

    private function matchesScope(CatalogProduct $product, string $scopeType, int $scopeId): bool
    {
        return match ($scopeType) {
            SearchRecommendedProduct::SCOPE_MAIN => true,
            SearchRecommendedProduct::SCOPE_DIRECTION => (int)($product->collection?->direction_id ?? 0) === $scopeId,
            SearchRecommendedProduct::SCOPE_CATEGORY => (int)($product->subcategory?->category_id ?? 0) === $scopeId,
            SearchRecommendedProduct::SCOPE_SUBCATEGORY => (int)($product->subcategory_id ?? 0) === $scopeId,
            default => false,
        };
    }

    private function isProductEligible(CatalogProduct $product): bool
    {
        return (bool)$product->is_active && !(bool)$product->is_custom;
    }

    private function formatScopeError(string $scopeType, ?string $scopeLabel, int $slot, string $message): string
    {
        $prefix = match ($scopeType) {
            SearchRecommendedProduct::SCOPE_MAIN => 'Рекомендуем (основной)',
            SearchRecommendedProduct::SCOPE_DIRECTION => 'Направление «' . ($scopeLabel ?? '') . '»',
            SearchRecommendedProduct::SCOPE_CATEGORY => 'Категория «' . ($scopeLabel ?? '') . '»',
            SearchRecommendedProduct::SCOPE_SUBCATEGORY => 'Подкатегория «' . ($scopeLabel ?? '') . '»',
            default => 'Рекомендации',
        };

        return $prefix . ', слот ' . ($slot + 1) . ': ' . $message;
    }

    private function rowKey(string $scopeType, int $scopeId, int $slot): string
    {
        return $scopeType . ':' . $scopeId . ':' . $slot;
    }

    /**
     * @return CatalogDirection[]
     */
    private function findActiveDirections(): array
    {
        return CatalogDirection::find()
            ->where(['is_active' => true])
            ->orderBy(['sort_order' => SORT_ASC, 'label' => SORT_ASC, 'id' => SORT_ASC])
            ->all();
    }

    /**
     * @return CatalogCategory[]
     */
    private function findActiveCategories(): array
    {
        return CatalogCategory::findActiveOrdered();
    }

    /**
     * @return CatalogSubcategory[]
     */
    private function findActiveSubcategoriesForCategory(int $categoryId): array
    {
        return CatalogSubcategory::find()
            ->where(['category_id' => $categoryId, 'is_active' => true])
            ->orderBy(['sort_order' => SORT_ASC, 'label' => SORT_ASC, 'id' => SORT_ASC])
            ->all();
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    private function normalizeSlotList(array $items, int $slotCount): array
    {
        $result = [];
        for ($slot = 0; $slot < $slotCount; $slot++) {
            if (isset($items[$slot])) {
                $result[] = $items[$slot];
            }
        }

        return $result;
    }

    /**
     * @param array<string, array<int, array<string, mixed>>> $groups
     * @return array<string, list<array<string, mixed>>>
     */
    private function normalizeGroupedLists(array $groups, int $slotCount): array
    {
        $result = [];
        foreach ($groups as $slug => $items) {
            if ($slug === '' || $slug === null) {
                continue;
            }
            $result[$slug] = $this->normalizeSlotList($items, $slotCount);
            if ($result[$slug] === []) {
                unset($result[$slug]);
            }
        }

        return $result;
    }

    /**
     * @param array<int, array<string, mixed>> $group
     */
    private function assignGroupedItem(array &$group, ?string $slug, int $slot, array $item): void
    {
        if ($slug === null || $slug === '') {
            return;
        }

        if (!isset($group[$slug])) {
            $group[$slug] = [];
        }

        $group[$slug][$slot] = $item;
    }

    /**
     * @param list<array{scope_id: int, slot: int, item: array<string, mixed>}> $rows
     * @param array<int, string> $orderMap
     * @return list<array{scope_id: int, slot: int, item: array<string, mixed>}>
     */
    private function flattenScopeRows(array $rows, array $orderMap = []): array
    {
        if ($orderMap === []) {
            usort($rows, static fn (array $a, array $b): int => [$a['slot'], $a['scope_id']] <=> [$b['slot'], $b['scope_id']]);

            return $rows;
        }

        usort($rows, function (array $a, array $b) use ($orderMap): int {
            $orderA = array_search($a['scope_id'], array_keys($orderMap), true);
            $orderB = array_search($b['scope_id'], array_keys($orderMap), true);
            $orderA = $orderA === false ? PHP_INT_MAX : $orderA;
            $orderB = $orderB === false ? PHP_INT_MAX : $orderB;

            return [$orderA, $a['slot'], $a['scope_id']] <=> [$orderB, $b['slot'], $b['scope_id']];
        });

        return $rows;
    }

    /**
     * @return array<int|string, mixed>
     */
    private function productEagerLoad(): array
    {
        return [
            'catalogProduct.image',
            'catalogProduct.video',
            'catalogProduct.collection.direction',
            'catalogProduct.subcategory.category',
            'catalogProduct.badge.image',
            'catalogProduct.layout',
            'catalogProduct.catalogModel.modelPrices',
            'catalogProduct.catalogModel.category',
            'catalogProduct.catalogModel.subcategory',
            'catalogProduct.catalogModel.collection',
            'catalogProduct.catalogModel.badge.image',
            'catalogProduct.catalogModel.layout',
            'catalogProduct.catalogModel.video',
            'catalogProduct.catalogModel.modelImages.media',
            'catalogProduct.catalogModel.modelInteriorImages.media',
            'catalogProduct.catalogModel.modelDimensionImages.media',
            'catalogProduct.fabricColor.catalogColor.swatchMedia',
            'catalogProduct.fabricColor.fabricCollection',
            'catalogProduct.fabricColor.catalogColor.colorImages.media',
        ];
    }
}
