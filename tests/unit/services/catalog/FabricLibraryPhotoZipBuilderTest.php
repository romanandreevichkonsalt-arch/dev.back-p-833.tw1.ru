<?php

namespace tests\unit\services\catalog;

use app\models\CatalogFabricCollection;
use app\models\CatalogFabricColor;
use app\models\MediaFile;
use app\services\catalog\FabricLibraryPhotoZipBuilder;
use Codeception\Test\Unit;
use ZipArchive;

class FabricLibraryPhotoZipBuilderTest extends Unit
{
    public function testBuildZipUsesTextureCollectionAndDesignCodePath(): void
    {
        if (!class_exists(ZipArchive::class)) {
            $this->markTestSkipped('ZipArchive extension is not available.');
        }

        $webroot = \Yii::getAlias('@webroot');
        $relativePath = 'uploads/test/fabric-library-photo-' . uniqid('', true) . '.jpg';
        $absolutePath = $webroot . '/' . $relativePath;
        if (!is_dir(dirname($absolutePath))) {
            mkdir(dirname($absolutePath), 0777, true);
        }
        file_put_contents(
            $absolutePath,
            base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7')
        );

        $targetZip = sys_get_temp_dir() . '/fabric-library-' . uniqid('', true) . '.zip';

        $collection = new CatalogFabricCollection([
            'name' => 'STRONG',
            'texture' => 'Букле',
            'is_active' => true,
        ]);
        $color = new CatalogFabricColor([
            'design_code' => '110',
            'is_active' => true,
        ]);
        $color->populateRelation('swatchMedia', new MediaFile([
            'path' => $relativePath,
            'filename' => 'strong-110.jpg',
            'mime' => 'image/jpeg',
            'size' => 10,
            'created_at' => date('Y-m-d H:i:s'),
            'kind' => MediaFile::KIND_IMAGE,
        ]));
        $collection->populateRelation('activeColors', [$color]);

        try {
            $result = (new FabricLibraryPhotoZipBuilder())->buildZip($targetZip, [$collection]);
            verify($result['fileCount'])->equals(1);

            $zip = new ZipArchive();
            verify($zip->open($targetZip))->true();
            verify($zip->numFiles)->equals(1);
            verify($zip->getNameIndex(0))->equals('Букле/STRONG/110.jpg');
            $zip->close();
        } finally {
            @unlink($targetZip);
            @unlink($absolutePath);
        }
    }
}
