<?php

namespace app\services\catalog;

use app\models\CatalogModel;
use app\services\cache\ApiCacheInvalidator;
use Yii;

/**
 * Привязка всех активных коллекций тканей ко всем моделям + пересборка SKU.
 */
final class CatalogModelFabricLinkAllService
{
    public function __construct(
        private readonly CatalogModelProductSyncService $productSync = new CatalogModelProductSyncService(),
    ) {
    }

    /**
     * @return array{links_added: int, models_synced: int}
     */
    public function linkAllActiveFabricsToAllModels(): array
    {
        @set_time_limit(0);

        $linksAdded = $this->insertMissingFabricLinks();
        $modelsSynced = 0;
        foreach ($this->getAllModelIds() as $modelId) {
            $model = CatalogModel::findOne((int)$modelId);
            if ($model === null) {
                continue;
            }
            $this->productSync->syncForModel($model);
            $modelsSynced++;
        }

        ApiCacheInvalidator::touch();

        return [
            'links_added' => $linksAdded,
            'models_synced' => $modelsSynced,
        ];
    }

    public function insertMissingFabricLinks(): int
    {
        $now = date('Y-m-d H:i:s');

        return (int)Yii::$app->db->createCommand(
            <<<'SQL'
INSERT INTO {{%catalog_model_fabric_collections}} (model_id, fabric_collection_id, created_at)
SELECT m.id, fc.id, :now
FROM {{%catalog_models}} m
CROSS JOIN {{%catalog_fabric_collections}} fc
WHERE fc.is_active = 1
  AND NOT EXISTS (
    SELECT 1
    FROM {{%catalog_model_fabric_collections}} mf
    WHERE mf.model_id = m.id AND mf.fabric_collection_id = fc.id
  )
SQL,
            ['now' => $now]
        )->execute();
    }

    /**
     * @return list<int>
     */
    public function getAllModelIds(): array
    {
        return array_map('intval', (new \yii\db\Query())
            ->select('id')
            ->from('{{%catalog_models}}')
            ->orderBy(['id' => SORT_ASC])
            ->column());
    }

    public function syncModelById(int $modelId): void
    {
        $model = CatalogModel::findOne($modelId);
        if ($model === null) {
            return;
        }

        $this->productSync->syncForModel($model);
    }
}
