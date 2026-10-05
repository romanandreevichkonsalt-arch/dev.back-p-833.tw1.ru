<?php

use app\models\CatalogModel;
use app\services\catalog\CatalogModelProductSyncService;
use yii\db\Migration;

/**
 * Привязка всех активных коллекций тканей ко всем моделям каталога + пересборка SKU.
 */
class m261002_140000_catalog_models_link_all_fabric_collections extends Migration
{
    public function safeUp(): void
    {
        $now = date('Y-m-d H:i:s');

        $this->db->createCommand(
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

        $syncService = \Yii::$container->get(CatalogModelProductSyncService::class);

        $modelIds = (new \yii\db\Query())
            ->select('id')
            ->from('{{%catalog_models}}')
            ->column($this->db);

        foreach ($modelIds as $modelId) {
            $model = CatalogModel::findOne((int)$modelId);
            if ($model !== null) {
                $syncService->syncForModel($model);
            }
        }
    }

    public function safeDown(): void
    {
        echo "m261002_140000_catalog_models_link_all_fabric_collections cannot be reverted.\n";
    }
}
