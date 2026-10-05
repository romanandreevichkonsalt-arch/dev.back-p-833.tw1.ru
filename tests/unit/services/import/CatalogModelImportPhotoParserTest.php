<?php

namespace tests\unit\services\import;

use app\services\import\catalog\CatalogModelImportPhotoParser;
use Codeception\Test\Unit;

class CatalogModelImportPhotoParserTest extends Unit
{
    public function testSplitUrlListWithGuillemetComma(): void
    {
        $urls = CatalogModelImportPhotoParser::splitUrlList(
            'https://a.example/1.jpg«, »https://b.example/2.jpg'
        );

        verify($urls)->equals([
            'https://a.example/1.jpg',
            'https://b.example/2.jpg',
        ]);
    }

    public function testCombinedFieldTreatsFolderAsTechFolderAndImagesAsGallery(): void
    {
        $parsed = CatalogModelImportPhotoParser::parseCombinedGalleryTechField(
            'https://a.example/1.jpg, https://b.example/2.jpg, '
            . 'https://drive.google.com/drive/folders/abc123'
        );

        verify($parsed['galleryPhotoUrls'])->equals([
            'https://a.example/1.jpg',
            'https://b.example/2.jpg',
        ]);
        verify($parsed['dimensionPhotoUrls'])->equals([]);
        verify($parsed['dimensionPhotoFolderUrl'])->equals('https://drive.google.com/drive/folders/abc123');
    }

    public function testDimensionFieldAcceptsImageOrFolder(): void
    {
        $folder = CatalogModelImportPhotoParser::parseDimensionField(
            'https://drive.google.com/drive/u/0/folders/1dsFvhCjF-DDj_IuR6yhU_M_6r7QyMTzt'
        );
        verify($folder['dimensionPhotoUrls'])->equals([]);
        verify($folder['dimensionPhotoFolderUrl'])->stringContainsString('folders/');

        $image = CatalogModelImportPhotoParser::parseDimensionField('https://cdn.example/tech.webp');
        verify($image['dimensionPhotoUrls'])->equals(['https://cdn.example/tech.webp']);
        verify($image['dimensionPhotoFolderUrl'])->null();
    }
}
