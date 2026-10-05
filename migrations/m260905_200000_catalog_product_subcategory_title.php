<?php

use app\models\CatalogModel;
use app\services\catalog\CatalogModelProductSyncService;
use yii\db\Migration;

class m260905_200000_catalog_product_subcategory_title extends Migration
{
    public function safeUp(): void
    {
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
        echo "m260905_200000_catalog_product_subcategory_title cannot be reverted.\n";
    }
}
