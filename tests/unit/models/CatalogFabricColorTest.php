<?php

namespace tests\unit\models;

use app\models\CatalogColor;
use app\models\CatalogFabricColor;
use Codeception\Test\Unit;

class CatalogFabricColorTest extends Unit
{
    public function testBuildApiLabelCombinesCollectionAndDesignCode(): void
    {
        verify(CatalogFabricColor::buildApiLabel('GUCCI', '422', 'Белый'))->equals('GUCCI 422');
        verify(CatalogFabricColor::buildApiLabel('Gucci', '001', null))->equals('Gucci 001');
        verify(CatalogFabricColor::buildApiLabel('', '001', 'Белый'))->equals('001');
        verify(CatalogFabricColor::buildApiLabel('GUCCI', '', null))->equals('GUCCI');
    }

    public function testGetCatalogColorNameReturnsRussianLabelFromCatalog(): void
    {
        $fabricColor = new CatalogFabricColor(['design_code' => '422']);
        $fabricColor->populateRelation('catalogColor', new CatalogColor(['label' => 'Бежевый']));

        verify($fabricColor->getCatalogColorName())->equals('Бежевый');
    }

    public function testGetCatalogColorNameIsNullWithoutCatalogColor(): void
    {
        $fabricColor = new CatalogFabricColor(['design_code' => '422']);

        verify($fabricColor->getCatalogColorName())->null();
    }

    public function testToApiPayloadIncludesColorName(): void
    {
        $fabricColor = new CatalogFabricColor([
            'design_code' => '422',
            'api_label' => 'GUCCI 422',
            'is_recommended_fabric' => true,
            'position_number' => 3,
        ]);
        $fabricColor->populateRelation('catalogColor', new CatalogColor(['label' => 'Белый', 'slug' => 'belyy']));

        $payload = $fabricColor->toApiPayload();
        verify($payload['colorName'])->equals('Белый');
        verify($payload['isRecommendedFabric'])->true();
        verify($payload['positionNumber'])->equals(3);
    }
}
