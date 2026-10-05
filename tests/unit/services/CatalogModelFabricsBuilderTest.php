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
use app\models\CatalogSubcategory;
use app\services\catalog\CatalogModelFabricsBuilder;
use app\services\catalog\CatalogModelProductSyncService;
use Codeception\Test\Unit;
use Yii;

class CatalogModelFabricsBuilderTest extends Unit
{
    private CatalogModelProductSyncService $syncService;

    protected function _before(): void
    {
        $this->syncService = Yii::$container->get(CatalogModelProductSyncService::class);
    }

    public function testBuildsTextureCollectionColorHierarchy(): void
    {
        $priceCategory3 = CatalogPriceCategory::findOne(['number' => 3]);
        verify($priceCategory3)->notNull();

        $direction = $this->createDirection('fabrics-builder-direction');
        $collection = $this->createCatalogCollection($direction, 'fabrics-builder-line');
        $category = $this->createCategory('fabrics-builder-category');
        $sub = $this->createSubcategory($category, 'fabrics-builder-sub');
        $velvetCollection = $this->createFabricCollection('fabrics-builder-velvet', $priceCategory3->id, 'Велюр');
        $velvetCollection->name = 'Gucci';
        $velvetCollection->save(false);
        $linenCollection = $this->createFabricCollection('fabrics-builder-linen', $priceCategory3->id, 'Лён');
        $linenCollection->name = 'Natural';
        $linenCollection->save(false);

        $gray = $this->createFabricColorLink($velvetCollection, 'gray', 'Серый');
        $beige = $this->createFabricColorLink($linenCollection, 'beige', 'Бежевый');

        $model = new CatalogModel([
            'collection_id' => $collection->id,
            'category_id' => $category->id,
            'subcategory_id' => $sub->id,
            'slug' => 'fabrics-builder-model',
            'title' => 'Модель для fabrics',
            'is_active' => true,
            'sort_order' => 0,
        ]);
        verify($model->save(false))->true();
        $model->syncPrices([$priceCategory3->id => '120 000 ₽']);
        $model->syncFabricCollectionLinks([$velvetCollection->id, $linenCollection->id]);
        $this->syncService->syncForModel($model);

        $model = CatalogModel::find()
            ->where(['id' => $model->id])
            ->with([
                'modelPrices.priceCategory',
                'fabricCollections.priceCategory',
                'fabricCollections.activeColors.catalogColor',
                'fabricCollections.activeColors.fabricCollection',
                'products',
            ])
            ->one();
        verify($model)->notNull();

        $productsByColorId = [];
        foreach ($model->products as $product) {
            if ($product->fabric_color_id !== null) {
                $productsByColorId[(int)$product->fabric_color_id] = $product;
            }
        }

        $fabrics = (new CatalogModelFabricsBuilder())->build($model, $productsByColorId);

        verify($fabrics)->arrayCount(2);
        verify($fabrics[0]['texture'])->equals('Велюр');
        verify($fabrics[0]['collections'])->arrayCount(1);
        verify($fabrics[0]['collections'][0]['id'])->equals('fabrics-builder-velvet');
        verify($fabrics[0]['collections'][0]['name'])->equals('Gucci');
        verify($fabrics[0]['collections'][0]['category'])->equals(3);
        verify($fabrics[0]['collections'][0]['price'])->equals('120 000 ₽');
        verify($fabrics[0]['collections'][0]['colors'])->arrayCount(1);
        verify($fabrics[0]['collections'][0]['colors'][0]['label'])->equals('Gucci Серый');
        verify($fabrics[0]['collections'][0]['colors'][0])->arrayHasKey('productSlug');
        verify($fabrics[0]['collections'][0]['colors'][0]['href'])->stringStartsWith('/product/');

        verify($fabrics[1]['texture'])->equals('Лён');
        verify($fabrics[1]['collections'][0]['colors'][0]['label'])->equals('Natural Бежевый');
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

    private function createCategory(string $slug): CatalogCategory
    {
        $category = new CatalogCategory([
            'slug' => $slug,
            'label' => $slug,
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

    private function createFabricCollection(string $slug, int $priceCategoryId, string $texture): CatalogFabricCollection
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
        CatalogFabricCollection $fabricCollection,
        string $colorSlug,
        string $colorLabel,
    ): CatalogFabricColor {
        $catalogColor = CatalogColor::findOne(['slug' => $colorSlug]);
        if ($catalogColor === null) {
            $catalogColor = new CatalogColor([
                'slug' => $colorSlug,
                'label' => $colorLabel,
                'sort_order' => 0,
                'is_active' => true,
            ]);
            $catalogColor->save(false);
        }

        $link = new CatalogFabricColor([
            'fabric_collection_id' => $fabricCollection->id,
            'color_id' => $catalogColor->id,
            'design_code' => $colorLabel,
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $link->save(false);

        return $link;
    }
}
