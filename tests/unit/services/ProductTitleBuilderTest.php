<?php

namespace tests\unit\services;

use app\models\CatalogCollection;
use app\models\CatalogColor;
use app\models\CatalogFabricCollection;
use app\models\CatalogFabricColor;
use app\models\CatalogModel;
use app\models\CatalogSubcategory;
use app\services\catalog\ProductTitleBuilder;
use Codeception\Test\Unit;

class ProductTitleBuilderTest extends Unit
{
    public function testBuildIncludesFabricCollectionAndDesignCode(): void
    {
        $model = $this->createModel('Прямой диван', 'Адриано');
        $color = $this->createColor('Neroli', '0', 'Белый');

        verify(ProductTitleBuilder::build($model, $color))->equals('Прямой диван Адриано Белый Neroli 0');
        verify(ProductTitleBuilder::buildSlug($model, $color))->equals('pryamoy-divan-adriano-belyy-neroli-0');
    }

    public function testBuildWithoutCatalogColorUsesDesignCodeAsColor(): void
    {
        $model = $this->createModel('Прямой диван', 'Адриано');
        $color = $this->createColor('Neroli', '422', null);

        verify(ProductTitleBuilder::build($model, $color))->equals('Прямой диван Адриано 422 Neroli 422');
    }

    public function testBuildWithoutDesignCode(): void
    {
        $model = $this->createModel('Прямой диван', 'Адриано');
        $color = $this->createColor('Neroli', '', 'Белый');

        verify(ProductTitleBuilder::build($model, $color))->equals('Прямой диван Адриано Белый Neroli');
    }

    public function testBuildDefaultUnchanged(): void
    {
        $model = $this->createModel('Прямой диван', 'Адриано');

        verify(ProductTitleBuilder::buildDefault($model))->equals('Прямой диван Адриано кастом');
        verify(ProductTitleBuilder::buildDefaultSlug($model))->equals('pryamoy-divan-adriano-kastom');
    }

    private function createModel(string $subcategoryLabel, string $collectionName): CatalogModel
    {
        $subcategory = new CatalogSubcategory(['label' => $subcategoryLabel]);
        $collection = new CatalogCollection(['name' => $collectionName]);

        $model = new CatalogModel();
        $model->populateRelation('subcategory', $subcategory);
        $model->populateRelation('collection', $collection);
        $model->populateRelation('category', null);

        return $model;
    }

    private function createColor(string $fabricName, string $designCode, ?string $catalogColorLabel): CatalogFabricColor
    {
        $fabricCollection = new CatalogFabricCollection(['name' => $fabricName]);
        $catalogColor = $catalogColorLabel !== null && $catalogColorLabel !== ''
            ? new CatalogColor(['label' => $catalogColorLabel])
            : null;

        $color = new CatalogFabricColor(['design_code' => $designCode]);
        $color->populateRelation('fabricCollection', $fabricCollection);
        $color->populateRelation('catalogColor', $catalogColor);

        return $color;
    }
}
