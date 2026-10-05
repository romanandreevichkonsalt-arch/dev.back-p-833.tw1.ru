<?php

namespace tests\unit\services;

use app\models\CatalogCategory;
use app\models\CatalogCollection;
use app\models\CatalogColor;
use app\models\CatalogDirection;
use app\models\CatalogFabricCollection;
use app\models\CatalogFabricColor;
use app\models\CatalogModel;
use app\models\CatalogPriceCategory;
use app\models\CatalogProduct;
use app\models\CatalogSubcategory;
use app\services\catalog\CatalogModelProductSyncService;
use Codeception\Test\Unit;
use Yii;

class CatalogModelProductSyncServiceTest extends Unit
{
    private CatalogModelProductSyncService $syncService;

    protected function _before(): void
    {
        $this->syncService = Yii::$container->get(CatalogModelProductSyncService::class);
    }

    public function testGeneratesProductsForLinkedFabricColors(): void
    {
        $priceCategory3 = $this->findPriceCategoryByNumber(3);
        $priceCategory5 = $this->findPriceCategoryByNumber(5);

        $direction = $this->createDirection('sync-test-direction');
        $collection = $this->createCatalogCollection($direction, 'sync-test-collection');
        $category = $this->createCategory('sync-test-category');
        $sub = $this->createSubcategory($category, 'sync-test-sub');
        $fabricA = $this->createFabricCollection('fa', $priceCategory3->id);
        $fabricB = $this->createFabricCollection('fb', $priceCategory5->id, 'Велюр');
        $colorA = $this->createFabricColorLink($fabricA, 'gray', 'Серый');
        $colorB = $this->createFabricColorLink($fabricB, 'beige', 'Бежевый');

        $model = new CatalogModel([
            'collection_id' => $collection->id,
            'category_id' => $category->id,
            'subcategory_id' => $sub->id,
            'slug' => 'sync-test-model',
            'title' => 'Тестовая модель',
            'is_active' => true,
            'sort_order' => 0,
        ]);
        verify($model->save(false))->true();

        $model->syncPrices([
            $priceCategory3->id => '120 000 ₽',
            $priceCategory5->id => '150 000 ₽',
        ]);
        $model->syncFabricCollectionLinks([$fabricA->id, $fabricB->id]);
        $this->syncService->syncForModel($model);

        $products = CatalogProduct::find()
            ->where(['model_id' => $model->id])
            ->andWhere(['not', ['fabric_color_id' => null]])
            ->indexBy('fabric_color_id')
            ->all();

        verify(count($products))->equals(2);
        verify($products[$colorA->id]->price_display)->equals('120 000 ₽');
        verify($products[$colorB->id]->price_display)->equals('150 000 ₽');
        verify($products[$colorA->id]->title)->equals('sync-test-sub sync-test-collection Серый fa gray');
        verify($products[$colorA->id]->slug)->equals('sync-test-sub-sync-test-collection-seryy-fa-gray');
        verify($products[$colorB->id]->title)->equals('sync-test-sub sync-test-collection Бежевый fb beige');
        verify($products[$colorB->id]->slug)->equals('sync-test-sub-sync-test-collection-bezhevyy-fb-beige');
        verify((bool)$products[$colorA->id]->is_custom)->false();
        verify((bool)$products[$colorB->id]->is_custom)->false();

        $defaultProduct = CatalogProduct::find()
            ->where(['model_id' => $model->id, 'fabric_color_id' => null])
            ->one();
        verify($defaultProduct)->notNull();
        verify((bool)$defaultProduct->is_custom)->true();
        verify($defaultProduct->slug)->notEmpty();

        $modelPayload = CatalogModel::find()
            ->where(['id' => $model->id])
            ->with(['products'])
            ->one()
            ->toCatalogApiPayload();
        verify($modelPayload['customProductSlug'])->equals($defaultProduct->slug);
    }

    public function testDeleteProductsForFabricColorRemovesSku(): void
    {
        $direction = $this->createDirection('sync-test-direction-del-color');
        $collection = $this->createCatalogCollection($direction, 'sync-test-collection-del-color');
        $category = $this->createCategory('sync-test-category-del-color');
        $sub = $this->createSubcategory($category, 'sync-test-sub-del-color');
        $fabric = $this->createFabricCollection('sync-del-fabric');
        $color = $this->createFabricColorLink($fabric, 'olive', 'Оливковый');

        $model = new CatalogModel([
            'collection_id' => $collection->id,
            'category_id' => $category->id,
            'subcategory_id' => $sub->id,
            'slug' => 'sync-test-model-del-color',
            'title' => 'Модель del color',
            'is_active' => true,
            'sort_order' => 0,
        ]);
        $model->save(false);
        $model->syncFabricCollectionLinks([$fabric->id]);
        $this->syncService->syncForModel($model);

        verify(CatalogProduct::find()->where(['fabric_color_id' => $color->id])->count())->equals(1);

        verify($this->syncService->deleteProductsForFabricColorIds([(int)$color->id]))->equals(1);
        verify(CatalogProduct::find()->where(['fabric_color_id' => $color->id])->count())->equals(0);
    }

    public function testSyncRemovesGhostProductsAfterFabricColorLoss(): void
    {
        $direction = $this->createDirection('sync-test-direction-ghost');
        $collection = $this->createCatalogCollection($direction, 'sync-test-collection-ghost');
        $category = $this->createCategory('sync-test-category-ghost');
        $sub = $this->createSubcategory($category, 'sync-test-sub-ghost');
        $fabric = $this->createFabricCollection('sync-ghost-fabric');
        $color = $this->createFabricColorLink($fabric, 'sand', 'Песочный');

        $model = new CatalogModel([
            'collection_id' => $collection->id,
            'category_id' => $category->id,
            'subcategory_id' => $sub->id,
            'slug' => 'sync-test-model-ghost',
            'title' => 'Модель ghost',
            'is_active' => true,
            'sort_order' => 0,
        ]);
        $model->save(false);
        $model->syncFabricCollectionLinks([$fabric->id]);
        $this->syncService->syncForModel($model);

        $ghost = CatalogProduct::find()->where(['fabric_color_id' => $color->id])->one();
        verify($ghost)->notNull();
        $ghost->fabric_color_id = null;
        $ghost->is_custom = false;
        $ghost->save(false);

        $this->syncService->syncForModel($model);

        verify(CatalogProduct::find()->where([
            'model_id' => $model->id,
            'is_custom' => false,
            'fabric_color_id' => null,
        ])->count())->equals(0);
    }

    public function testDeactivatesProductsWhenModelIsInactive(): void
    {
        $direction = $this->createDirection('sync-test-direction-inactive-model');
        $collection = $this->createCatalogCollection($direction, 'sync-test-collection-inactive-model');
        $category = $this->createCategory('sync-test-category-inactive-model');
        $sub = $this->createSubcategory($category, 'sync-test-sub-inactive-model');
        $fabric = $this->createFabricCollection('sync-inactive-model-fabric');
        $color = $this->createFabricColorLink($fabric, 'coal', 'Угольный');

        $model = new CatalogModel([
            'collection_id' => $collection->id,
            'category_id' => $category->id,
            'subcategory_id' => $sub->id,
            'slug' => 'sync-test-model-inactive',
            'title' => 'Модель inactive',
            'is_active' => true,
            'sort_order' => 0,
        ]);
        $model->save(false);
        $model->syncFabricCollectionLinks([$fabric->id]);
        $this->syncService->syncForModel($model);

        $product = CatalogProduct::find()->where(['fabric_color_id' => $color->id])->one();
        verify($product)->notNull();
        verify((bool)$product->is_active)->true();

        $model->is_active = false;
        $model->save(false);
        $this->syncService->syncForModel($model);

        $product->refresh();
        verify((bool)$product->is_active)->false();
    }

    public function testRemovesProductsWhenFabricUnlinked(): void
    {
        $direction = $this->createDirection('sync-test-direction-2');
        $collection = $this->createCatalogCollection($direction, 'sync-test-collection-2');
        $category = $this->createCategory('sync-test-category-2');
        $sub = $this->createSubcategory($category, 'sync-test-sub-2');
        $fabric = $this->createFabricCollection('sync-test-fabric-2');
        $this->createFabricColorLink($fabric, 'blue', 'Синий');

        $model = new CatalogModel([
            'collection_id' => $collection->id,
            'category_id' => $category->id,
            'subcategory_id' => $sub->id,
            'slug' => 'sync-test-model-2',
            'title' => 'Модель 2',
            'is_active' => true,
            'sort_order' => 0,
        ]);
        $model->save(false);
        $model->syncFabricCollectionLinks([$fabric->id]);
        $this->syncService->syncForModel($model);

        verify(CatalogProduct::find()->where(['model_id' => $model->id])->andWhere(['not', ['fabric_color_id' => null]])->count())->equals(1);

        $model->syncFabricCollectionLinks([]);
        $this->syncService->syncForModel($model);

        verify(CatalogProduct::find()->where(['model_id' => $model->id])->andWhere(['not', ['fabric_color_id' => null]])->count())->equals(0);
        verify(CatalogProduct::find()->where(['model_id' => $model->id, 'fabric_color_id' => null])->count())->equals(1);
    }

    private function findPriceCategoryByNumber(int $number): CatalogPriceCategory
    {
        $category = CatalogPriceCategory::findOne(['number' => $number]);
        verify($category)->notNull();

        return $category;
    }

    private function createDirection(string $slug): CatalogDirection
    {
        $direction = new CatalogDirection([
            'slug' => $slug,
            'label' => $slug,
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $direction->save(false);

        return $direction;
    }

    private function createCategory(string $slug, string $label = ''): CatalogCategory
    {
        $category = new CatalogCategory([
            'slug' => $slug,
            'label' => $label !== '' ? $label : $slug,
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $category->save(false);

        return $category;
    }

    private function createSubcategory(CatalogCategory $category, string $slug): CatalogSubcategory
    {
        $sub = new CatalogSubcategory([
            'category_id' => $category->id,
            'slug' => $slug,
            'label' => $slug,
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $sub->save(false);

        return $sub;
    }

    private function createCatalogCollection(CatalogDirection $direction, string $slug): CatalogCollection
    {
        $collection = new CatalogCollection([
            'direction_id' => $direction->id,
            'slug' => $slug,
            'name' => $slug,
            'label' => 'Коллекция',
            'title' => $slug,
            'href' => '/catalog/' . $slug,
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $collection->save(false);

        return $collection;
    }

    private function createFabricCollection(string $slug, ?int $priceCategoryId = null, ?string $texture = null): CatalogFabricCollection
    {
        $fabric = new CatalogFabricCollection([
            'slug' => $slug,
            'name' => $slug,
            'texture' => $texture,
            'price_category_id' => $priceCategoryId,
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $fabric->save(false);

        return $fabric;
    }

    private function createFabricColorLink(
        CatalogFabricCollection $fabric,
        string $slug,
        string $label
    ): CatalogFabricColor {
        $colorSlug = $fabric->slug . '-' . $slug;
        $catalogColor = CatalogColor::findOne(['slug' => $colorSlug]);
        if ($catalogColor === null) {
            $catalogColor = new CatalogColor([
                'slug' => $colorSlug,
                'label' => $label,
                'sort_order' => 0,
                'is_active' => true,
            ]);
            $catalogColor->save(false);
        }

        $link = new CatalogFabricColor([
            'fabric_collection_id' => $fabric->id,
            'color_id' => $catalogColor->id,
            'design_code' => $slug,
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $link->save(false);

        return $link;
    }
}
