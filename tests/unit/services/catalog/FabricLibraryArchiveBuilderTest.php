<?php

namespace tests\unit\services\catalog;

use app\models\CatalogColor;
use app\models\CatalogFabricCollection;
use app\models\CatalogFabricColor;
use app\models\MediaFile;
use app\services\catalog\FabricLibraryPdfRenderer;
use Codeception\Test\Unit;

class FabricLibraryArchiveBuilderTest extends Unit
{
    public function testPdfHtmlIncludesFormFieldsAndOmitsInternalPricingFields(): void
    {
        $collection = new CatalogFabricCollection([
            'slug' => 'gucci',
            'name' => 'GUCCI',
            'material_kind' => CatalogFabricCollection::MATERIAL_KIND_FABRIC,
            'texture' => 'Букле',
            'density_gsm' => 320,
            'roll_width_cm' => 140,
            'martindale' => 50000,
            'description' => 'Описание',
            'composition' => '100% polyester',
            'care_instructions' => 'Сухая чистка',
            'meter_price_display' => '700 ₽/м',
            'price_category_id' => 3,
            'price_category_line1_id' => 5,
            'is_active' => true,
        ]);

        $color = new CatalogFabricColor([
            'design_code' => '422',
            'is_active' => true,
            'is_recommended_fabric' => true,
            'position_number' => 7,
            'api_label' => 'GUCCI 422',
        ]);
        $color->populateRelation('catalogColor', new CatalogColor(['label' => 'Белый']));
        $color->populateRelation('swatchMedia', new MediaFile([
            'path' => 'uploads/media/fabrics/2026/01/gucci-422-swatch.jpg',
            'filename' => 'gucci-422-swatch.jpg',
            'mime' => 'image/jpeg',
            'kind' => MediaFile::KIND_IMAGE,
        ]));
        $collection->populateRelation('activeColors', [$color]);

        $html = (new FabricLibraryPdfRenderer())->renderHtmlForCollections([$collection]);

        verify(str_contains($html, 'GUCCI'))->true();
        verify(str_contains($html, 'Категория ткани'))->true();
        verify(str_contains($html, 'Букле'))->true();
        verify(str_contains($html, '100% polyester'))->true();
        verify(str_contains($html, '422'))->true();
        verify(str_contains($html, 'Белый'))->true();
        verify(str_contains($html, '700 ₽/м'))->false();
        verify(str_contains($html, 'gucci'))->false();
        verify(str_contains($html, 'Реком'))->false();
        verify(str_contains($html, 'position'))->false();
        verify(str_contains($html, 'pbr'))->false();
    }
}
