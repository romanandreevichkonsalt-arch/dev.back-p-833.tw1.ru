<?php

namespace tests\unit\models;

use app\models\CatalogCategory;
use app\models\CatalogCollection;
use app\models\CatalogDirection;
use app\models\CatalogModel;
use app\models\CatalogSubcategory;
use Codeception\Test\Unit;

class CatalogModelSlugTest extends Unit
{
    public function testSlugFromTitle(): void
    {
        $suffix = uniqid('slug-', false);

        $direction = CatalogDirection::findOne(['slug' => 'a-plus']);
        if ($direction === null) {
            $direction = new CatalogDirection([
                'slug' => 'a-plus',
                'label' => 'А+',
                'sort_order' => 0,
                'is_active' => true,
            ]);
            $direction->save(false);
        }

        $collection = new CatalogCollection([
            'direction_id' => (int)$direction->id,
            'slug' => $suffix . '-artemida',
            'name' => 'Артемида',
            'label' => 'Коллекция',
            'title' => 'Артемида',
            'href' => '/catalog/' . $suffix . '-artemida',
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $collection->save(false);

        $category = new CatalogCategory([
            'slug' => $suffix . '-sofa',
            'label' => 'Диван',
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $category->save(false);

        $subcategory = new CatalogSubcategory([
            'category_id' => (int)$category->id,
            'slug' => $suffix . '-corner',
            'label' => 'Угловые диваны',
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $subcategory->save(false);

        $model = new CatalogModel([
            'collection_id' => (int)$collection->id,
            'category_id' => (int)$category->id,
            'subcategory_id' => (int)$subcategory->id,
            'title' => 'Угловой диван Артемида',
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $model->applyTitleSlug();

        verify($model->slug)->equals($suffix . '-artemida-uglovoy-divan-artemida');
    }
}
