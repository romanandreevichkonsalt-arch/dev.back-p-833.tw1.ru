<?php

namespace tests\unit\models;

use app\models\CatalogListingTileSettings;
use Codeception\Test\Unit;

class CatalogListingTileSettingsTest extends Unit
{
    public function testClampFloorGuideLimitsToTileHeight(): void
    {
        verify(CatalogListingTileSettings::clampFloorGuide(999, 347))->equals(346);
    }

    public function testClampFloorGuideLimitsNegativeToZero(): void
    {
        verify(CatalogListingTileSettings::clampFloorGuide(-10, 347))->equals(0);
    }
}
