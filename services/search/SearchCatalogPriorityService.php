<?php

namespace app\services\search;

use app\models\CatalogCollection;
use app\models\CatalogDirection;
use app\models\CatalogModel;
use app\models\CatalogProduct;
use app\models\SearchCatalogPriorityModel;
use app\modules\admin\helpers\HomePageProductsHelper;
use app\services\cache\ApiCacheInvalidator;
use yii\db\Expression;

class SearchCatalogPriorityService
{
    /**
     * @return array<string, mixed>
     */
    public function buildAdminFormData(?int $activeDirectionId = null): array
    {
        $directions = $this->findActiveDirections();
        if ($directions === []) {
            return [
                'directions' => [],
                'activeDirectionId' => 0,
            ];
        }

        $directionIds = array_map(static fn (CatalogDirection $direction): int => (int)$direction->id, $directions);
        if ($activeDirectionId === null || !in_array($activeDirectionId, $directionIds, true)) {
            $activeDirectionId = $directionIds[0];
        }

        $rowsByDirection = [];
        foreach (SearchCatalogPriorityModel::find()
            ->with(['catalogModel.collection', 'catalogModel.subcategory', 'sampleCatalogProduct.fabricColor'])
            ->orderBy(['direction_id' => SORT_ASC, 'sort_order' => SORT_ASC, 'id' => SORT_ASC])
            ->all() as $row) {
            $rowsByDirection[(int)$row->direction_id][] = $row;
        }

        $directionPayload = [];
        foreach ($directions as $direction) {
            $directionId = (int)$direction->id;
            $items = [];
            foreach ($rowsByDirection[$directionId] ?? [] as $row) {
                $items[] = $this->buildAdminItemFromRow($row);
            }

            $directionPayload[] = [
                'id' => $directionId,
                'label' => $direction->label,
                'slug' => $direction->slug,
                'items' => $items,
            ];
        }

        return [
            'directions' => $directionPayload,
            'activeDirectionId' => $activeDirectionId,
        ];
    }

    /**
     * Образцы SKU для выдачи поиска: сначала направление «Линия 1», затем sort_order.
     *
     * @param list<int>|null $restrictToProductIds
     * @param list<int>|null $restrictToDirectionIds только направления из текущей выдачи
     * @return list<int>
     */
    public function getOrderedSampleProductIds(?array $restrictToProductIds = null, ?array $restrictToDirectionIds = null): array
    {
        if (\Yii::$app->db->schema->getTableSchema(SearchCatalogPriorityModel::tableName(), true) === null) {
            return [];
        }

        $restrict = null;
        if ($restrictToProductIds !== null && $restrictToProductIds !== []) {
            $restrict = array_fill_keys(array_map(static fn ($id): int => (int)$id, $restrictToProductIds), true);
        }

        $query = SearchCatalogPriorityModel::find()
            ->alias('scp')
            ->innerJoin(['d' => CatalogDirection::tableName()], 'd.id = scp.direction_id')
            ->where(['>', 'scp.sample_catalog_product_id', 0]);

        if ($restrictToDirectionIds !== null && $restrictToDirectionIds !== []) {
            $query->andWhere([
                'scp.direction_id' => array_map(static fn ($id): int => (int)$id, $restrictToDirectionIds),
            ]);
        }

        $rows = $query
            ->orderBy([
                new Expression("CASE WHEN [[d]].[[slug]] = 'line-1' THEN 0 ELSE 1 END"),
                'scp.sort_order' => SORT_ASC,
                'scp.id' => SORT_ASC,
            ])
            ->all();

        $ids = [];
        foreach ($rows as $row) {
            $productId = (int)$row->sample_catalog_product_id;
            if ($productId <= 0) {
                continue;
            }
            if ($restrict !== null && !isset($restrict[$productId])) {
                continue;
            }
            $ids[] = $productId;
        }

        return $ids;
    }

    /**
     * @return array{enabled: bool, directionId: int, directionLabel: string, sampleCatalogProductId: int, sortOrder: int, nextSortOrder: int}
     */
    public function getStateForModel(CatalogModel $model): array
    {
        $defaults = [
            'enabled' => false,
            'directionId' => 0,
            'directionLabel' => '',
            'sampleCatalogProductId' => 0,
            'sortOrder' => 0,
            'nextSortOrder' => 1,
        ];

        if ($model->isNewRecord) {
            return $defaults;
        }

        $collection = $model->collection ?? CatalogCollection::findOne((int)$model->collection_id);
        $directionId = (int)($collection?->direction_id ?? 0);
        if ($directionId <= 0) {
            return $defaults;
        }

        $direction = CatalogDirection::findOne($directionId);
        $row = SearchCatalogPriorityModel::findOne([
            'direction_id' => $directionId,
            'catalog_model_id' => (int)$model->id,
        ]);

        $sortOrder = max(0, (int)($row?->sort_order ?? 0));

        return [
            'enabled' => true,
            'directionId' => $directionId,
            'directionLabel' => (string)($direction?->label ?? ''),
            'sampleCatalogProductId' => (int)($row?->sample_catalog_product_id ?? 0),
            'sortOrder' => $sortOrder,
            'nextSortOrder' => $this->nextSortOrderForDirection($directionId),
        ];
    }

    public function saveFromModelForm(CatalogModel $model, array $post): ?string
    {
        if ($model->isNewRecord) {
            return null;
        }

        if (\Yii::$app->db->schema->getTableSchema(SearchCatalogPriorityModel::tableName(), true) === null) {
            return null;
        }

        $collection = $model->collection ?? CatalogCollection::findOne((int)$model->collection_id);
        $directionId = (int)($collection?->direction_id ?? 0);
        if ($directionId <= 0) {
            return null;
        }

        $payload = $post['catalog_listing_priority'] ?? [];
        if (!is_array($payload)) {
            return null;
        }

        $productId = (int)($payload['sample_catalog_product_id'] ?? 0);
        $sortOrderRaw = (int)($payload['sort_order'] ?? 0);
        $directionLabel = (string)(CatalogDirection::findOne($directionId)?->label ?? '');

        $existing = SearchCatalogPriorityModel::findOne([
            'direction_id' => $directionId,
            'catalog_model_id' => (int)$model->id,
        ]);

        if ($productId <= 0) {
            if ($existing !== null) {
                $existing->delete();
                ApiCacheInvalidator::touch();
            }

            return null;
        }

        $productError = $this->validateSampleProduct(
            $productId,
            (int)$model->id,
            $directionId,
            $directionLabel,
            1
        );
        if ($productError !== null) {
            return $productError;
        }

        if ($existing === null) {
            $existing = new SearchCatalogPriorityModel([
                'direction_id' => $directionId,
                'catalog_model_id' => (int)$model->id,
            ]);
        }

        $existing->sample_catalog_product_id = $productId;
        $existing->sort_order = $sortOrderRaw > 0
            ? $sortOrderRaw
            : ($existing->isNewRecord
                ? $this->nextSortOrderForDirection($directionId)
                : max(1, (int)$existing->sort_order));
        if (!$existing->save(false)) {
            return 'Не удалось сохранить приоритетный товар для поиска.';
        }

        ApiCacheInvalidator::touch();

        return null;
    }

    /**
     * @return list<string>
     */
    public function saveFromPost(array $post): array
    {
        $payload = $post['catalog_priority'] ?? [];
        if (!is_array($payload)) {
            return ['Некорректные данные формы.'];
        }

        $errors = [];
        /** @var array<int, list<array{catalog_model_id: int, sort_order: int}>> $desiredByDirection */
        $desiredByDirection = [];

        foreach ($this->findActiveDirections() as $direction) {
            $directionId = (int)$direction->id;
            $itemsPost = $payload[$directionId] ?? $payload[(string)$directionId] ?? [];
            if (!is_array($itemsPost)) {
                continue;
            }

            $seenModels = [];
            foreach ($itemsPost as $index => $itemPost) {
                if (!is_array($itemPost)) {
                    continue;
                }

                $modelId = (int)($itemPost['catalog_model_id'] ?? 0);
                if ($modelId <= 0) {
                    continue;
                }

                if (isset($seenModels[$modelId])) {
                    $errors[] = sprintf(
                        'Направление «%s»: модель #%d указана несколько раз.',
                        $direction->label,
                        $modelId
                    );
                    continue;
                }
                $seenModels[$modelId] = true;

                $catalogModel = CatalogModel::find()
                    ->where(['id' => $modelId])
                    ->with('collection')
                    ->one();
                if ($catalogModel === null) {
                    $errors[] = sprintf('Направление «%s», строка %d: модель не найдена.', $direction->label, (int)$index + 1);
                    continue;
                }

                if (!$this->modelBelongsToDirection($catalogModel, $directionId)) {
                    $errors[] = sprintf(
                        'Направление «%s», строка %d: модель «%s» не относится к этому направлению.',
                        $direction->label,
                        (int)$index + 1,
                        $catalogModel->title
                    );
                    continue;
                }

                $productId = (int)($itemPost['sample_catalog_product_id'] ?? 0);
                if ($productId <= 0) {
                    $errors[] = sprintf(
                        'Направление «%s», строка %d: выберите товар-образец для модели «%s».',
                        $direction->label,
                        (int)$index + 1,
                        $catalogModel->title
                    );
                    continue;
                }

                $productError = $this->validateSampleProduct(
                    $productId,
                    $modelId,
                    $directionId,
                    $direction->label,
                    (int)$index + 1
                );
                if ($productError !== null) {
                    $errors[] = $productError;
                    continue;
                }

                $desiredByDirection[$directionId][] = [
                    'catalog_model_id' => $modelId,
                    'sample_catalog_product_id' => $productId,
                    'sort_order' => $this->parseSortOrderFromPost($itemPost['sort_order'] ?? null),
                ];
            }
        }

        if ($errors !== []) {
            return $errors;
        }

        $transaction = \Yii::$app->db->beginTransaction();
        try {
            SearchCatalogPriorityModel::deleteAll([]);
            foreach ($desiredByDirection as $directionId => $items) {
                foreach ($items as $item) {
                    $sortOrder = max(1, (int)$item['sort_order']);
                    $row = new SearchCatalogPriorityModel([
                        'direction_id' => $directionId,
                        'catalog_model_id' => $item['catalog_model_id'],
                        'sample_catalog_product_id' => $item['sample_catalog_product_id'],
                        'sort_order' => $sortOrder,
                    ]);
                    if (!$row->save(false)) {
                        throw new \RuntimeException('Не удалось сохранить приоритет каталога.');
                    }
                }
            }
            $transaction->commit();
            ApiCacheInvalidator::touch();
        } catch (\Throwable) {
            $transaction->rollBack();

            return ['Не удалось сохранить приоритетные модели каталога.'];
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildAdminItemFromRow(SearchCatalogPriorityModel $row): array
    {
        $model = $row->catalogModel;
        $productLabel = '';
        $swatchStyle = '';
        $sampleProductId = (int)($row->sample_catalog_product_id ?? 0);
        $product = $row->sampleCatalogProduct;
        if ($product === null && $sampleProductId > 0) {
            $product = CatalogProduct::find()
                ->where(['id' => $sampleProductId])
                ->with(['fabricColor', 'catalogModel'])
                ->one();
        }
        if ($product !== null) {
            $picker = HomePageProductsHelper::pickerItemFromProduct($product);
            $productLabel = (string)($picker['productTitle'] ?: $picker['title']);
            $swatchStyle = (string)($picker['swatchStyle'] ?? '');
            $sampleProductId = (int)$product->id;
        }

        $sortOrder = (int)$row->sort_order;

        return [
            'catalog_model_id' => (int)$row->catalog_model_id,
            'sample_catalog_product_id' => $sampleProductId > 0 ? $sampleProductId : '',
            'sort_order' => $sortOrder > 0 ? $sortOrder : 1,
            'model_title' => $model?->title ?? ('Модель #' . $row->catalog_model_id),
            'subcategory_label' => $model?->subcategory?->label ?? '',
            'collection_label' => $model?->collection?->getDisplayName() ?? '',
            'product_search' => $productLabel,
            'swatch_style' => $swatchStyle,
        ];
    }

    private function nextSortOrderForDirection(int $directionId): int
    {
        $max = SearchCatalogPriorityModel::find()
            ->where(['direction_id' => $directionId])
            ->max('sort_order');

        return max(1, (int)$max + 1);
    }

    private function parseSortOrderFromPost(mixed $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        return max(1, (int)$value);
    }

    private function validateSampleProduct(
        int $productId,
        int $modelId,
        int $directionId,
        string $directionLabel,
        int $rowNumber,
    ): ?string {
        $product = CatalogProduct::find()
            ->where(['id' => $productId])
            ->with(['collection'])
            ->one();
        if ($product === null) {
            return sprintf('Направление «%s», строка %d: товар не найден.', $directionLabel, $rowNumber);
        }

        if ((int)($product->model_id ?? 0) !== $modelId) {
            return sprintf(
                'Направление «%s», строка %d: выбранный товар не принадлежит указанной модели.',
                $directionLabel,
                $rowNumber
            );
        }

        if (!(bool)$product->is_active || (bool)$product->is_custom) {
            return sprintf(
                'Направление «%s», строка %d: товар неактивен или кастомный.',
                $directionLabel,
                $rowNumber
            );
        }

        if ((int)($product->collection?->direction_id ?? 0) !== $directionId) {
            return sprintf(
                'Направление «%s», строка %d: товар не относится к этому направлению.',
                $directionLabel,
                $rowNumber
            );
        }

        return null;
    }

    private function resolveDefaultProductIdForModel(int $modelId, int $directionId): int
    {
        $product = CatalogProduct::find()
            ->alias('p')
            ->innerJoin(['col' => CatalogCollection::tableName()], 'col.id = p.collection_id')
            ->where([
                'p.model_id' => $modelId,
                'p.is_active' => true,
                'p.is_custom' => false,
                'col.direction_id' => $directionId,
            ])
            ->orderBy(['p.sort_order' => SORT_ASC, 'p.id' => SORT_ASC])
            ->one();

        return $product !== null ? (int)$product->id : 0;
    }

    private function modelBelongsToDirection(CatalogModel $model, int $directionId): bool
    {
        $collection = $model->collection;
        if ($collection !== null && (int)$collection->direction_id === $directionId) {
            return true;
        }

        return CatalogProduct::find()
            ->alias('p')
            ->innerJoin(['col' => CatalogCollection::tableName()], 'col.id = p.collection_id')
            ->where([
                'p.model_id' => (int)$model->id,
                'p.is_active' => true,
                'p.is_custom' => false,
                'col.direction_id' => $directionId,
            ])
            ->exists();
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
}
