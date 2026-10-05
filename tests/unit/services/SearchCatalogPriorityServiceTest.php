<?php

namespace tests\unit\services;

use app\models\CatalogCollection;
use app\models\CatalogDirection;
use app\models\CatalogModel;
use app\models\CatalogProduct;
use app\models\SearchCatalogPriorityModel;
use app\services\search\SearchCatalogPriorityService;
use Codeception\Test\Unit;

class SearchCatalogPriorityServiceTest extends Unit
{
    protected function _before(): void
    {
        if (\Yii::$app->db->schema->getTableSchema(SearchCatalogPriorityModel::tableName(), true) === null) {
            $this->markTestSkipped('search_catalog_priority_models is not migrated in test DB.');
        }
    }

    public function testSaveRejectsModelFromAnotherDirection(): void
    {
        $directionA = CatalogDirection::find()->where(['slug' => 'a-plus'])->one();
        $directionB = CatalogDirection::find()->where(['slug' => 'line-1'])->one();
        if ($directionA === null || $directionB === null) {
            $this->markTestSkipped('Directions are not seeded.');
        }

        $foreignModel = CatalogModel::find()
            ->alias('m')
            ->innerJoin(['col' => CatalogCollection::tableName()], 'col.id = m.collection_id')
            ->where(['col.direction_id' => $directionB->id])
            ->one();

        if ($foreignModel === null) {
            $this->markTestSkipped('No model for direction B.');
        }

        $service = new SearchCatalogPriorityService();
        $errors = $service->saveFromPost([
            'catalog_priority' => [
                (int)$directionA->id => [
                    0 => [
                        'catalog_model_id' => (int)$foreignModel->id,
                        'sort_order' => 10,
                    ],
                ],
            ],
        ]);

        verify($errors)->notEmpty();
    }

    public function testSaveAndLoadOrderedSampleProductIds(): void
    {
        $direction = CatalogDirection::find()->where(['is_active' => true])->orderBy(['id' => SORT_ASC])->one();
        $model = CatalogModel::find()
            ->alias('m')
            ->innerJoin(['col' => CatalogCollection::tableName()], 'col.id = m.collection_id')
            ->where(['col.direction_id' => $direction->id])
            ->orderBy(['m.id' => SORT_ASC])
            ->one();

        $product = CatalogProduct::find()
            ->where(['model_id' => $model?->id, 'is_active' => true, 'is_custom' => false])
            ->orderBy(['id' => SORT_ASC])
            ->one();

        if ($direction === null || $model === null || $product === null) {
            $this->markTestSkipped('No direction/model/product pair.');
        }

        SearchCatalogPriorityModel::deleteAll();

        $service = new SearchCatalogPriorityService();
        $errors = $service->saveFromPost([
            'catalog_priority' => [
                (int)$direction->id => [
                    0 => [
                        'catalog_model_id' => (int)$model->id,
                        'sample_catalog_product_id' => (int)$product->id,
                        'sort_order' => 5,
                    ],
                ],
            ],
        ]);

        verify($errors)->equals([]);
        verify($service->getOrderedSampleProductIds())->equals([(int)$product->id]);
    }
}
