<?php

namespace tests\unit\services;

use app\models\CatalogCollection;
use app\models\CatalogDirection;
use app\models\CatalogProduct;
use app\models\CatalogSubcategory;
use app\models\SearchRecommendedProduct;
use app\services\search\SearchRecommendedService;
use Codeception\Test\Unit;

class SearchRecommendedServiceTest extends Unit
{
    public function testSaveRejectsProductFromAnotherDirection(): void
    {
        $directionA = CatalogDirection::find()->where(['slug' => 'a-plus'])->one();
        $directionB = CatalogDirection::find()->where(['slug' => 'line-1'])->one();
        if ($directionA === null || $directionB === null) {
            $this->markTestSkipped('Directions are not seeded.');
        }

        $product = CatalogProduct::find()
            ->alias('p')
            ->innerJoin(['col' => CatalogCollection::tableName()], 'col.id = p.collection_id')
            ->where(['col.direction_id' => $directionB->id, 'p.is_active' => true, 'p.is_custom' => false])
            ->one();

        if ($product === null) {
            $this->markTestSkipped('No product for direction B.');
        }

        $service = new SearchRecommendedService();
        $errors = $service->saveFromPost([
            'recommended' => [
                'direction' => [
                    (int)$directionA->id => [
                        0 => ['catalog_product_id' => (int)$product->id],
                    ],
                ],
            ],
        ]);

        verify($errors)->notEmpty();
    }

    public function testSaveAndBuildBootstrapPayload(): void
    {
        $product = CatalogProduct::find()
            ->where(['is_active' => true, 'is_custom' => false])
            ->orderBy(['id' => SORT_ASC])
            ->one();

        if ($product === null) {
            $this->markTestSkipped('No active catalog product.');
        }

        SearchRecommendedProduct::deleteAll();

        $service = new SearchRecommendedService();
        $errors = $service->saveFromPost([
            'recommended' => [
                'main' => [
                    0 => ['catalog_product_id' => (int)$product->id],
                ],
            ],
        ]);

        verify($errors)->equals([]);

        $payload = $service->buildBootstrapPayload();
        verify($payload)->arrayHasKey('recommended');
        verify($payload)->arrayHasKey('recommendedGroups');
        verify($payload['recommended'])->notEmpty();
        verify($payload['recommendedGroups']['main'])->notEmpty();
    }

    public function testSaveRejectsProductFromAnotherSubcategory(): void
    {
        $subcategories = CatalogSubcategory::find()
            ->alias('s')
            ->innerJoin(['p' => CatalogProduct::tableName()], 'p.subcategory_id = s.id')
            ->where(['p.is_active' => true, 'p.is_custom' => false])
            ->limit(2)
            ->all();

        if (count($subcategories) < 2) {
            $this->markTestSkipped('Not enough subcategories with products.');
        }

        $targetSubcategory = $subcategories[0];
        $otherSubcategory = $subcategories[1];

        $product = CatalogProduct::find()
            ->where([
                'subcategory_id' => $otherSubcategory->id,
                'is_active' => true,
                'is_custom' => false,
            ])
            ->one();

        if ($product === null) {
            $this->markTestSkipped('No product in other subcategory.');
        }

        $service = new SearchRecommendedService();
        $errors = $service->saveFromPost([
            'recommended' => [
                'subcategory' => [
                    (int)$targetSubcategory->id => [
                        0 => ['catalog_product_id' => (int)$product->id],
                    ],
                ],
            ],
        ]);

        verify($errors)->notEmpty();
    }
}
