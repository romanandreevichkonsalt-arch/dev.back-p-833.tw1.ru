<?php

namespace tests\unit\helpers;

use app\helpers\CatalogFabricBaseColorPalette;
use Codeception\Test\Unit;

class CatalogFabricBaseColorPaletteTest extends Unit
{
    public function testResolvesHexByLabelAndSlug(): void
    {
        verify(CatalogFabricBaseColorPalette::hexForLabel('Бежевый'))->equals('#D4C4A8');
        verify(CatalogFabricBaseColorPalette::hexForSlug('zheltyy'))->equals('#E6C84A');
        verify(CatalogFabricBaseColorPalette::resolveHex('Терракота', 'terrakota'))->equals('#C86B4A');
    }
}
