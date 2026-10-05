<?php

namespace tests\unit\models;

use app\models\CatalogCategory;
use app\models\CatalogCollection;
use app\models\CatalogColor;
use app\models\CatalogDirection;
use app\models\CatalogFabricCollection;
use app\models\CatalogFabricColor;
use app\models\CatalogModel;
use app\models\CatalogPriceCategory;
use app\models\CatalogSubcategory;
use Codeception\Test\Unit;

class CatalogModelListingSwatchesTest extends Unit
{
    public function testCollectListingSwatchesPayloadUsesUniqueCatalogColors(): void
    {
        $priceCategory = CatalogPriceCategory::findOne(['number' => 3]);
        verify($priceCategory)->notNull();

        $direction = new CatalogDirection([
            'slug' => 'listing-swatches-direction',
            'label' => 'Listing swatches direction',
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $direction->save(false);

        $collection = new CatalogCollection([
            'direction_id' => $direction->id,
            'slug' => 'listing-swatches-line',
            'name' => 'listing-swatches-line',
            'label' => 'Line',
            'title' => 'Line',
            'href' => '/catalog/listing-swatches-line',
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $collection->save(false);

        $category = new CatalogCategory([
            'slug' => 'listing-swatches-category',
            'label' => 'Category',
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $category->save(false);

        $subcategory = new CatalogSubcategory([
            'category_id' => $category->id,
            'slug' => 'listing-swatches-sub',
            'label' => 'Sub',
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $subcategory->save(false);

        $gray = $this->createCatalogColor('listing-gray', 'Серый', '#545453');
        $beige = $this->createCatalogColor('listing-beige', 'Бежевый', '#c9b8a0');
        $brown = $this->createCatalogColor('listing-brown', 'Коричневый', '#6b4a32');
        $green = $this->createCatalogColor('listing-green', 'Зелёный', '#2f5d3a');

        $velvet = new CatalogFabricCollection([
            'slug' => 'listing-swatches-velvet',
            'name' => 'Velvet',
            'texture' => 'Велюр',
            'price_category_id' => $priceCategory->id,
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $velvet->save(false);

        $linen = new CatalogFabricCollection([
            'slug' => 'listing-swatches-linen',
            'name' => 'Linen',
            'texture' => 'Лён',
            'price_category_id' => $priceCategory->id,
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $linen->save(false);

        $this->createFabricColor($velvet, $gray, '422');
        $this->createFabricColor($linen, $beige, '101');
        $this->createFabricColor($velvet, $brown, '901');
        $this->createFabricColor($linen, $gray, '777');
        $this->createFabricColor($velvet, $green, '555');

        $model = new CatalogModel([
            'collection_id' => $collection->id,
            'category_id' => $category->id,
            'subcategory_id' => $subcategory->id,
            'slug' => 'listing-swatches-model',
            'title' => 'Listing swatches model',
            'is_active' => true,
            'sort_order' => 0,
        ]);
        verify($model->save(false))->true();
        $model->syncFabricCollectionLinks([$velvet->id, $linen->id]);

        $model = CatalogModel::find()
            ->where(['id' => $model->id])
            ->with([
                'fabricCollections.activeColors.catalogColor.swatchMedia',
                'fabricCollections.activeColors.swatchMedia',
            ])
            ->one();
        verify($model)->notNull();

        $payload = $model->collectListingSwatchesPayload();

        verify($payload['swatchCount'])->equals(4);
        verify($payload['swatches'])->arrayCount(3);
        verify($payload['swatches'][0]['hexColor'])->equals('#545453');
        verify($payload['swatches'][0]['alt'])->equals('Серый');
        verify($payload['swatches'][1]['hexColor'])->equals('#6b4a32');
        verify($payload['swatches'][1]['alt'])->equals('Коричневый');
        verify($payload['swatches'][2]['hexColor'])->equals('#2f5d3a');
        verify($payload['swatches'][2]['alt'])->equals('Зелёный');
    }

    public function testCollectListingSwatchesPayloadSkipsDedupeWhenAtMostThreeColors(): void
    {
        $priceCategory = CatalogPriceCategory::findOne(['number' => 3]);
        verify($priceCategory)->notNull();

        $direction = new CatalogDirection([
            'slug' => 'listing-swatches-small-direction',
            'label' => 'Small swatches direction',
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $direction->save(false);

        $collection = new CatalogCollection([
            'direction_id' => $direction->id,
            'slug' => 'listing-swatches-small-line',
            'name' => 'listing-swatches-small-line',
            'label' => 'Line',
            'title' => 'Line',
            'href' => '/catalog/listing-swatches-small-line',
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $collection->save(false);

        $category = new CatalogCategory([
            'slug' => 'listing-swatches-small-category',
            'label' => 'Category',
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $category->save(false);

        $subcategory = new CatalogSubcategory([
            'category_id' => $category->id,
            'slug' => 'listing-swatches-small-sub',
            'label' => 'Sub',
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $subcategory->save(false);

        $gray = $this->createCatalogColor('listing-small-gray', 'Серый', '#545453');
        $beige = $this->createCatalogColor('listing-small-beige', 'Бежевый', '#c9b8a0');

        $velvet = new CatalogFabricCollection([
            'slug' => 'listing-swatches-small-velvet',
            'name' => 'Velvet',
            'texture' => 'Велюр',
            'price_category_id' => $priceCategory->id,
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $velvet->save(false);

        $linen = new CatalogFabricCollection([
            'slug' => 'listing-swatches-small-linen',
            'name' => 'Linen',
            'texture' => 'Лён',
            'price_category_id' => $priceCategory->id,
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $linen->save(false);

        $this->createFabricColor($velvet, $gray, '422');
        $this->createFabricColor($linen, $beige, '101');
        $this->createFabricColor($velvet, $gray, '777');

        $model = new CatalogModel([
            'collection_id' => $collection->id,
            'category_id' => $category->id,
            'subcategory_id' => $subcategory->id,
            'slug' => 'listing-swatches-small-model',
            'title' => 'Listing swatches small model',
            'is_active' => true,
            'sort_order' => 0,
        ]);
        verify($model->save(false))->true();
        $model->syncFabricCollectionLinks([$velvet->id, $linen->id]);

        $model = CatalogModel::find()
            ->where(['id' => $model->id])
            ->with([
                'fabricCollections.activeColors.catalogColor.swatchMedia',
                'fabricCollections.activeColors.swatchMedia',
            ])
            ->one();
        verify($model)->notNull();

        $payload = $model->collectListingSwatchesPayload();

        verify($payload['swatchCount'])->equals(3);
        verify($payload['swatches'])->arrayCount(3);
        verify($payload['swatches'][0]['alt'])->equals('Серый');
        verify($payload['swatches'][1]['alt'])->equals('Серый');
        verify($payload['swatches'][2]['alt'])->equals('Бежевый');
    }

    private function createCatalogColor(string $slug, string $label, string $hex): CatalogColor
    {
        $color = CatalogColor::findOne(['slug' => $slug]);
        if ($color === null) {
            $color = new CatalogColor([
                'slug' => $slug,
                'label' => $label,
                'hex_color' => $hex,
                'sort_order' => 0,
                'is_active' => true,
            ]);
            $color->save(false);

            return $color;
        }

        $color->label = $label;
        $color->hex_color = $hex;
        $color->save(false);

        return $color;
    }

    private function createFabricColor(
        CatalogFabricCollection $fabricCollection,
        CatalogColor $catalogColor,
        string $designCode,
    ): CatalogFabricColor {
        $link = new CatalogFabricColor([
            'fabric_collection_id' => $fabricCollection->id,
            'color_id' => $catalogColor->id,
            'design_code' => $designCode,
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $link->save(false);

        return $link;
    }
}
