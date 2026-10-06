<?php

namespace app\services\catalog;

use app\models\CatalogColor;
use app\models\CatalogFabricColor;
use yii\db\ActiveQuery;
use yii\db\Expression;

class CatalogLibraryFabricsService
{
    /**
     * @param array<string, mixed> $params
     * @return array{items: list<array<string, mixed>>, meta: array{total: int, page: int, perPage: int}}
     */
    public function list(array $params): array
    {
        $page = max(1, (int)($params['page'] ?? 1));
        $perPage = min(100, max(1, (int)($params['perPage'] ?? 24)));
        $query = $this->createQuery($params);

        $total = (int)$query->count();
        $fabrics = $query
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->all();

        $items = [];
        foreach ($fabrics as $fabric) {
            $items[] = $this->mapFabricItem($fabric);
        }

        return [
            'items' => $items,
            'meta' => $this->buildListMeta($total, $page, $perPage),
        ];
    }

    /**
     * @param array<string, mixed> $params
     * @return array{total: int, page: int, perPage: int}
     */
    public function countMeta(array $params): array
    {
        $page = max(1, (int)($params['page'] ?? 1));
        $perPage = min(100, max(1, (int)($params['perPage'] ?? 24)));
        $total = (int)$this->createQuery($params)->count();

        return $this->buildListMeta($total, $page, $perPage);
    }

    /**
     * @return array{total: int, page: int, perPage: int, texturesArchiveUrl?: string}
     */
    private function buildListMeta(int $total, int $page, int $perPage): array
    {
        $meta = [
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
        ];

        if (FabricLibraryArchiveUrls::exists()) {
            $meta['texturesArchiveUrl'] = FabricLibraryArchiveUrls::publicUrl();
        }

        return $meta;
    }

    /**
     * @param array<string, mixed> $params
     */
    private function createQuery(array $params): ActiveQuery
    {
        $q = trim((string)($params['q'] ?? ''));
        $texture = trim((string)($params['texture'] ?? ''));
        $colorSlugs = $this->stringListParam($params['color'] ?? null);

        $query = CatalogFabricColor::find()
            ->alias('fc')
            ->innerJoin(
                ['fcol' => '{{%catalog_fabric_collections}}'],
                'fcol.id = fc.fabric_collection_id AND fcol.is_active = 1'
            )
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

        $this->applyLibrarySort($query);

        return $query;
    }

    private function applyLibrarySort(ActiveQuery $query): void
    {
        $query->orderBy([
            new Expression('CASE WHEN [[fc]].[[is_recommended_fabric]] = 1 THEN 0 ELSE 1 END'),
            new Expression(
                'CASE WHEN [[fc]].[[is_recommended_fabric]] = 1'
                . ' THEN COALESCE([[fc]].[[position_number]], 2147483647) ELSE 0 END'
            ),
            new Expression('COALESCE(NULLIF([[fc]].[[api_label]], \'\'), [[fc]].[[design_code]])'),
            'fc.id' => SORT_ASC,
        ]);
    }

    /**
     * @return list<string>
     */
    private function stringListParam(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_string($value)) {
            $value = array_map('trim', explode(',', $value));
        }

        if (!is_array($value)) {
            return [];
        }

        $slugs = [];
        foreach ($value as $item) {
            $slug = trim((string)$item);
            if ($slug !== '') {
                $slugs[] = $slug;
            }
        }

        return array_values(array_unique($slugs));
    }

    /**
     * @return array<string, mixed>
     */
    private function mapFabricItem(CatalogFabricColor $fabric): array
    {
        $swatches = $fabric->collectSwatchesApiPayload();

        $collection = $fabric->fabricCollection;
        $composition = $collection !== null ? trim((string)($collection->composition ?? '')) : '';
        $martindale = $collection !== null && $collection->martindale !== null
            ? (int)$collection->martindale
            : null;

        return [
            'fabricColorId' => (int)$fabric->id,
            'slug' => $fabric->getSlug(),
            'label' => $fabric->getApiLabel(),
            'collection' => $collection?->name,
            'designCode' => $fabric->design_code,
            'colorId' => $fabric->catalogColor?->slug,
            'colorName' => $fabric->getCatalogColorName(),
            'hexColor' => $fabric->getHexColor(),
            'texture' => $collection?->texture,
            'composition' => $composition !== '' ? $composition : null,
            'martindale' => $martindale,
            'isRecommendedFabric' => (bool)$fabric->is_recommended_fabric,
            'positionNumber' => $fabric->position_number !== null ? (int)$fabric->position_number : null,
            'description' => $fabric->getDescriptionForApi(),
            'swatch' => $swatches[0] ?? null,
        ];
    }
}
