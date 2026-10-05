<?php

namespace app\services\catalog;

use app\models\CatalogFabricCollection;
use app\models\CatalogModel;
use app\models\CatalogModelPrice;
use app\models\CatalogPriceCategory;
use Yii;

class CatalogPriceCategoryService
{
    public function __construct(
        private readonly CatalogModelProductSyncService $syncService,
    ) {
    }

    public function delete(CatalogPriceCategory $category): void
    {
        $fallbackId = $this->resolveFallbackId((int)$category->id);

        $affectedFabricIds = CatalogFabricCollection::find()
            ->select('id')
            ->where(['price_category_id' => $category->id])
            ->column();

        CatalogFabricCollection::updateAll(
            ['price_category_id' => $fallbackId],
            ['price_category_id' => $category->id]
        );

        $affectedModelIds = CatalogModelPrice::find()
            ->select('model_id')
            ->where(['price_category_id' => $category->id])
            ->column();

        CatalogModelPrice::deleteAll(['price_category_id' => $category->id]);

        $category->delete();

        $modelIds = array_unique(array_merge(
            $affectedModelIds,
            $this->findModelIdsByFabricCollections($affectedFabricIds)
        ));

        foreach ($modelIds as $modelId) {
            $model = CatalogModel::findOne((int)$modelId);
            if ($model !== null) {
                $this->syncService->syncForModel($model);
            }
        }
    }

    /**
     * @param int[] $fabricCollectionIds
     * @return int[]
     */
    private function findModelIdsByFabricCollections(array $fabricCollectionIds): array
    {
        if ($fabricCollectionIds === []) {
            return [];
        }

        return Yii::$app->db->createCommand(
            'SELECT DISTINCT model_id FROM {{%catalog_model_fabric_collections}} WHERE fabric_collection_id IN ('
            . implode(',', array_map('intval', $fabricCollectionIds))
            . ')'
        )->queryColumn();
    }

    private function resolveFallbackId(int $excludeId): ?int
    {
        $fallback = CatalogPriceCategory::find()
            ->where(['is_active' => true])
            ->andWhere(['<>', 'id', $excludeId])
            ->orderBy(['sort_order' => SORT_ASC, 'number' => SORT_ASC, 'id' => SORT_ASC])
            ->one();

        return $fallback !== null ? (int)$fallback->id : null;
    }
}
