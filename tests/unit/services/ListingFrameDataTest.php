<?php

namespace tests\unit\services;

use app\services\media\ListingFrameData;
use Codeception\Test\Unit;

class ListingFrameDataTest extends Unit
{
    public function testInitialScaleFitsWideImageWithoutCoverZoom(): void
    {
        $scale = ListingFrameData::initialScaleForImage(2000, 800, 458, 347);

        verify($scale)->lessThan(1.0);
        verify($scale)->greaterThan(0.4);
    }

    public function testInitialScaleIsOneWhenDimensionsUnknown(): void
    {
        verify(ListingFrameData::initialScaleForImage(0, 800, 458, 347))->equals(1.0);
    }
}
