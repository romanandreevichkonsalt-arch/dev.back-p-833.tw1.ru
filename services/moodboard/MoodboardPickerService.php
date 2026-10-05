<?php

namespace app\services\moodboard;

use app\models\CatalogCategory;
use app\models\CatalogCollection;
use app\models\CatalogColor;
use app\models\CatalogFabricColor;
use app\models\CatalogModel;
use app\models\CatalogSurfaceMaterial;
use app\models\MoodboardObjectType;
use yii\db\ActiveQuery;
use yii\db\Expression;

class MoodboardPickerService
{
    /**
     * @return array{items: list<array<string, mixed>>, meta: array{total: int, page: int, limit: int}}
     */
    public function categories(): array
    {
        $items = CatalogCategory::find()
            ->where(['is_active' => true])
            ->orderBy(['sort_order' => SORT_ASC, 'label' => SORT_ASC])
            ->all();

        $mapped = [];
        foreach ($items as $category) {
            $mapped[] = [
                'slug' => $category->slug,
                'label' => $category->label,
                'sortOrder' => (int)$category->sort_order,
            ];
        }

        $total = count($mapped);

        return [
            'items' => $mapped,
            'meta' => [
                'total' => $total,
                'page' => 1,
                'limit' => $total,
            ],
        ];
    }

    /**
     * @return array{items: list<array<string, mixed>>, meta: array{total: int, page: int, limit: int}}
     */
    public function colors(): array
    {
        $q = trim((string)\Yii::$app->request->get('q', ''));

        $query = CatalogColor::find()
            ->where(['is_active' => true])
            ->orderBy(['sort_order' => SORT_ASC, 'label' => SORT_ASC]);

        if ($q !== '') {
            $query->andWhere(['like', 'label', $q]);
        }

        $items = [];
        foreach ($query->all() as $color) {
            $items[] = [
                'id' => $color->slug,
                'label' => $color->label,
                'hexColor' => $color->hex_color ?: null,
            ];
        }

        $total = count($items);

        return [
            'items' => $items,
            'meta' => [
                'total' => $total,
                'page' => 1,
                'limit' => $total,
            ],
        ];
    }

    /**
     * @return array{items: list<array<string, mixed>>, meta: array{total: int, page: int, limit: int}}
     */
    public function models(): array
    {
        $pagination = MoodboardPagination::fromRequest(MoodboardPagination::DEFAULT_LIMIT);
        $query = $this->buildModelQuery();

        $total = (int)(clone $query)->count('DISTINCT m.id');

        $models = $query
            ->offset($pagination['offset'])
            ->limit($pagination['limit'])
            ->all();

        $items = [];
        foreach ($models as $model) {
            $items[] = $model->toMoodboardPickerApiItem();
        }

        return [
            'items' => $items,
            'meta' => MoodboardPagination::meta($total, $pagination['page'], $pagination['limit']),
        ];
    }

    /**
     * @return ActiveQuery
     */
    private function buildModelQuery(): ActiveQuery
    {
        $categorySlug = trim((string)\Yii::$app->request->get('category', ''));
        $collectionSlug = trim((string)\Yii::$app->request->get('collection', ''));
        $q = trim((string)\Yii::$app->request->get('q', ''));
        $colorSlugs = $this->stringListParam('color');

        $query = CatalogModel::find()
            ->alias('m')
            ->innerJoin(['c' => CatalogCollection::tableName()], 'c.id = m.collection_id AND c.is_active = 1')
            ->innerJoin(['cat' => CatalogCategory::tableName()], 'cat.id = m.category_id AND cat.is_active = 1')
            ->with(['collection', 'category', 'modelImages.media', 'modelInteriorImages.media'])
            ->where(['m.is_active' => true]);

        if ($categorySlug !== '') {
            $query->andWhere(['cat.slug' => $categorySlug]);
        }

        if ($collectionSlug !== '') {
            $query->andWhere(['c.slug' => $collectionSlug]);
        }

        if ($q !== '') {
            $query->andWhere(['like', 'm.title', $q]);
        }

        if ($colorSlugs !== []) {
            $query->innerJoin(['p' => '{{%catalog_products}}'], 'p.model_id = m.id AND p.is_active = 1')
                ->innerJoin(['fc' => CatalogFabricColor::tableName()], 'fc.id = p.fabric_color_id AND fc.is_active = 1')
                ->innerJoin(['col' => CatalogColor::tableName()], 'col.id = fc.color_id AND col.is_active = 1')
                ->andWhere(['col.slug' => $colorSlugs])
                ->groupBy('m.id');
        }

        $query->orderBy([
            new Expression('COALESCE(NULLIF(c.title, \'\'), c.name) ASC'),
            'm.title' => SORT_ASC,
        ]);

        return $query;
    }

    /**
     * @return array{items: list<array<string, mixed>>, meta: array{total: int, page: int, limit: int}}
     */
    public function fabrics(): array
    {
        $pagination = MoodboardPagination::fromRequest(MoodboardPagination::DEFAULT_LIMIT);
        $q = trim((string)\Yii::$app->request->get('q', ''));
        $colorSlugs = $this->stringListParam('color');
        $texture = trim((string)\Yii::$app->request->get('texture', ''));

        $query = CatalogFabricColor::find()
            ->alias('fc')
            ->innerJoin(['fcol' => '{{%catalog_fabric_collections}}'], 'fcol.id = fc.fabric_collection_id AND fcol.is_active = 1')
            ->with(['fabricCollection', 'catalogColor', 'swatchMedia'])
            ->where(['fc.is_active' => true]);

        if ($q !== '') {
            $query->andWhere([
                'or',
                ['like', 'fc.design_code', $q],
                ['like', 'fc.api_label', $q],
                ['like', 'fcol.name', $q],
            ]);
        }

        if ($texture !== '') {
            $query->andWhere(['fcol.texture' => $texture]);
        }

        if ($colorSlugs !== []) {
            $query->innerJoin(['col' => CatalogColor::tableName()], 'col.id = fc.color_id AND col.is_active = 1')
                ->andWhere(['col.slug' => $colorSlugs]);
        }

        $query->orderBy([
            'fcol.name' => SORT_ASC,
            new Expression('CASE WHEN [[fc]].[[position_number]] IS NULL THEN 1 ELSE 0 END'),
            'fc.position_number' => SORT_ASC,
            'fc.design_code' => SORT_ASC,
        ]);

        $total = (int)$query->count();

        $fabrics = $query
            ->offset($pagination['offset'])
            ->limit($pagination['limit'])
            ->all();

        $items = [];
        foreach ($fabrics as $fabric) {
            $swatches = $fabric->collectSwatchesApiPayload();
            $items[] = [
                'fabricColorId' => (int)$fabric->id,
                'slug' => $fabric->getSlug(),
                'label' => $fabric->getApiLabel(),
                'collection' => $fabric->fabricCollection?->name,
                'designCode' => $fabric->design_code,
                'colorId' => $fabric->catalogColor?->slug,
                'colorName' => $fabric->getCatalogColorName(),
                'hexColor' => $fabric->getHexColor(),
                'texture' => $fabric->fabricCollection?->texture,
                'isRecommendedFabric' => (bool)$fabric->is_recommended_fabric,
                'positionNumber' => $fabric->position_number !== null ? (int)$fabric->position_number : null,
                'swatch' => $swatches[0] ?? null,
            ];
        }

        return [
            'items' => $items,
            'meta' => MoodboardPagination::meta($total, $pagination['page'], $pagination['limit']),
        ];
    }

    /**
     * @return array{items: list<array<string, mixed>>, meta: array{total: int, page: int, limit: int}}
     */
    public function surfaceMaterials(): array
    {
        $pagination = MoodboardPagination::fromRequest(MoodboardPagination::DEFAULT_LIMIT);
        $q = trim((string)\Yii::$app->request->get('q', ''));
        $materialType = trim((string)\Yii::$app->request->get('materialType', ''));

        $query = CatalogSurfaceMaterial::find()
            ->with(['photoMedia', 'textureMedia'])
            ->where(['is_active' => true]);

        if ($materialType !== '') {
            $query->andWhere(['material_type' => $materialType]);
        }

        if ($q !== '') {
            $query->andWhere(['like', 'name', $q]);
        }

        $query->orderBy(['material_type' => SORT_ASC, 'sort_order' => SORT_ASC, 'name' => SORT_ASC]);

        $total = (int)$query->count();

        $materials = $query
            ->offset($pagination['offset'])
            ->limit($pagination['limit'])
            ->all();

        $items = [];
        foreach ($materials as $material) {
            $items[] = [
                'slug' => $material->slug,
                'name' => $material->name,
                'materialType' => $material->material_type,
                'photo' => $material->photoMedia?->toApiImagePayload($material->name),
                'texture' => $material->textureMedia?->toApiImagePayload($material->name),
            ];
        }

        return [
            'items' => $items,
            'meta' => MoodboardPagination::meta($total, $pagination['page'], $pagination['limit']),
        ];
    }

    /**
     * Стартовый набор для экрана создания мудборда (один round-trip).
     * Query те же, что у отдельных picker-*: limit (default 3 для models/fabrics/surfaceMaterials), page, category, collection, color[], q, texture, materialType.
     *
     * @return array{
     *     categories: array{items: list<array<string, mixed>>, meta: array{total: int, page: int, limit: int}},
     *     colors: array{items: list<array<string, mixed>>, meta: array{total: int, page: int, limit: int}},
     *     models: array{items: list<array<string, mixed>>, meta: array{total: int, page: int, limit: int}},
     *     fabrics: array{items: list<array<string, mixed>>, meta: array{total: int, page: int, limit: int}},
     *     surfaceMaterials: array{items: list<array<string, mixed>>, meta: array{total: int, page: int, limit: int}},
     *     objectTypes: list<array<string, mixed>>
     * }
     */
    public function bootstrap(): array
    {
        return [
            'categories' => $this->categories(),
            'colors' => $this->colors(),
            'models' => $this->models(),
            'fabrics' => $this->fabrics(),
            'surfaceMaterials' => $this->surfaceMaterials(),
            'objectTypes' => $this->objectTypes(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function objectTypes(): array
    {
        $types = MoodboardObjectType::find()
            ->where(['is_active' => true])
            ->orderBy(['code' => SORT_ASC])
            ->all();

        $items = [];
        foreach ($types as $type) {
            $items[] = [
                'code' => $type->code,
                'label' => $type->label,
                'schemaVersion' => (int)$type->schema_version,
            ];
        }

        return $items;
    }

    /**
     * @return list<string>
     */
    private function stringListParam(string $name): array
    {
        $request = \Yii::$app->request;
        $raw = $request->get($name, []);
        if (!is_array($raw)) {
            $raw = $raw !== '' && $raw !== null ? [(string)$raw] : [];
        }

        $values = [];
        foreach ($raw as $item) {
            $item = trim((string)$item);
            if ($item !== '') {
                $values[] = $item;
            }
        }

        return array_values(array_unique($values));
    }
}
