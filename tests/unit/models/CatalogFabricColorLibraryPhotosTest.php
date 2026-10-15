<?php

namespace tests\unit\models;

use app\models\CatalogFabricColor;
use app\models\MediaFile;
use Codeception\Test\Unit;

class CatalogFabricColorLibraryPhotosTest extends Unit
{
    public function testCollectLibraryPhotosApiPayloadUsesOnlyLibraryLinks(): void
    {
        $fabricColor = new CatalogFabricColor(['design_code' => '422']);
        $media = new MediaFile([
            'id' => 10,
            'filename' => 'sample.jpg',
            'mime' => 'image/jpeg',
        ]);
        $link = new \stdClass();
        $link->media = $media;
        $fabricColor->populateRelation('libraryPhotos', [$link]);

        $payload = $fabricColor->collectLibraryPhotosApiPayload();

        verify($payload)->arrayCount(1);
        verify($payload[0])->arrayHasKey('src');
    }
}
