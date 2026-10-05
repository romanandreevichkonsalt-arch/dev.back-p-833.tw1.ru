<?php

namespace tests\unit\services;

use app\models\CatalogCategory;
use app\models\CatalogCollection;
use app\models\CatalogColor;
use app\models\CatalogDirection;
use app\models\CatalogFabricCollection;
use app\models\CatalogFabricColor;
use app\models\CatalogModel;
use app\models\CatalogProduct;
use app\models\CatalogSubcategory;
use app\services\catalog\CatalogProductDetailService;
use Codeception\Test\Unit;
use Yii;

class CatalogProductDetailServiceTest extends Unit
{
    private CatalogProductDetailService $detail;
    private string $prefix;

    protected function _before(): void
    {
        $this->detail = Yii::$container->get(CatalogProductDetailService::class);
        $this->prefix = 'rrd' . substr(uniqid(), -8);
    }

    public function testRecommendedUsesRoundRobinAndSkipsCustomAndCurrent(): void
    {
        $fixture = $this->createFixture();
        $payload = $this->detail->getBySlug($fixture['currentSlug']);

        $slugs = array_column($payload['recommended'], 'slug');
        verify($slugs)->equals($fixture['expectedRecommended']);
        verify(in_array($fixture['currentSlug'], $slugs, true))->false();
        verify(in_array($fixture['customSlug'], $slugs, true))->false();
    }

    /**
     * @return array{currentSlug: string, customSlug: string, expectedRecommended: list<string>}
     */
    private function createFixture(): array
    {
        $direction = $this->createDirection($this->prefix . '-dir');
        $category = $this->createCategory($this->prefix . '-cat');
        $sub = $this->createSubcategory($category, $this->prefix . '-sub');
        $fabric = $this->createFabricCollection($this->prefix . '-fab');
        $color1 = $this->createFabricColor($fabric, 'c1', 'Цвет1', 1);
        $color2 = $this->createFabricColor($fabric, 'c2', 'Цвет2', 2);

        $names = ['adriano', 'apollo', 'athena', 'venice'];
        $models = [];
        foreach ($names as $index => $name) {
            $collection = $this->createCatalogCollection($direction, $this->prefix . '-' . $name, $index + 1);
            $models[$name] = $this->createModel($collection, $category, $sub, $name, $index + 1);
        }

        foreach ([1, 2] as $round) {
            foreach ($names as $name) {
                $color = $round === 1 ? $color1 : $color2;
                $this->createSku($models[$name], $color, $this->prefix . '-' . $name . '-c' . $round);
            }
        }

        $currentSlug = $this->prefix . '-adriano-c1';
        $expected = [
            $this->prefix . '-adriano-c2',
            $this->prefix . '-apollo-c1',
            $this->prefix . '-athena-c1',
            $this->prefix . '-venice-c1',
            $this->prefix . '-apollo-c2',
            $this->prefix . '-athena-c2',
            $this->prefix . '-venice-c2',
        ];

        $customSlug = $this->prefix . '-adriano-custom';
        $custom = new CatalogProduct([
            'slug' => $customSlug,
            'title' => 'Адриано кастом',
            'href' => '/catalog/' . $customSlug,
            'is_active' => true,
            'is_custom' => true,
            'model_id' => $models['adriano']->id,
            'collection_id' => $models['adriano']->collection_id,
            'subcategory_id' => $sub->id,
            'fabric_color_id' => null,
            'sort_order' => 1,
        ]);
        verify($custom->save(false))->true();

        return [
            'currentSlug' => $currentSlug,
            'customSlug' => $customSlug,
            'expectedRecommended' => $expected,
        ];
    }

    private function createSku(CatalogModel $model, CatalogFabricColor $color, string $slug): CatalogProduct
    {
        $product = new CatalogProduct([
            'slug' => $slug,
            'title' => $slug,
            'href' => '/catalog/' . $slug,
            'is_active' => true,
            'is_custom' => false,
            'model_id' => $model->id,
            'collection_id' => $model->collection_id,
            'subcategory_id' => $model->subcategory_id,
            'fabric_color_id' => $color->id,
            'sort_order' => $model->sort_order,
        ]);
        verify($product->save(false))->true();

        return $product;
    }

    private function createModel(
        CatalogCollection $collection,
        CatalogCategory $category,
        CatalogSubcategory $sub,
        string $name,
        int $sortOrder
    ): CatalogModel {
        $model = new CatalogModel([
            'collection_id' => $collection->id,
            'category_id' => $category->id,
            'subcategory_id' => $sub->id,
            'slug' => $this->prefix . '-m-' . $name,
            'title' => $name,
            'is_active' => true,
            'sort_order' => $sortOrder,
        ]);
        verify($model->save(false))->true();

        return $model;
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

    private function createCatalogCollection(CatalogDirection $direction, string $slug, int $sortOrder): CatalogCollection
    {
        $collection = new CatalogCollection([
            'direction_id' => $direction->id,
            'slug' => $slug,
            'name' => $slug,
            'label' => 'Коллекция',
            'title' => $slug,
            'href' => '/catalog/' . $slug,
            'sort_order' => $sortOrder,
            'is_active' => true,
        ]);
        $collection->save(false);

        return $collection;
    }

    private function createFabricCollection(string $slug): CatalogFabricCollection
    {
        $fabric = new CatalogFabricCollection([
            'slug' => $slug,
            'name' => $slug,
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $fabric->save(false);

        return $fabric;
    }

    private function createFabricColor(
        CatalogFabricCollection $fabric,
        string $suffix,
        string $label,
        int $sortOrder
    ): CatalogFabricColor {
        $catalogColor = new CatalogColor([
            'slug' => $fabric->slug . '-' . $suffix,
            'label' => $label,
            'sort_order' => $sortOrder,
            'is_active' => true,
        ]);
        $catalogColor->save(false);

        $link = new CatalogFabricColor([
            'fabric_collection_id' => $fabric->id,
            'color_id' => $catalogColor->id,
            'design_code' => $label,
            'sort_order' => $sortOrder,
            'is_active' => true,
        ]);
        $link->save(false);

        return $link;
    }
}
