<?php

namespace tests\unit\services;

use app\models\MediaFile;
use Codeception\Test\Unit;

class MediaImageVariantTest extends Unit
{
    public function testBuildApiImagePayloadUsesLargeForPageContent(): void
    {
        $payload = MediaFile::buildApiImagePayload(
            true,
            [
                'original' => '/uploads/media/banners/sample.png',
                'large' => '/uploads/media/banners/sample_l.webp',
                'medium' => '/uploads/media/banners/sample_m.webp',
                'mini' => '/uploads/media/banners/sample_s.webp',
            ],
            'Banner',
            1920,
            1080,
            null,
            'large'
        );

        verify($payload['src'])->equals('/uploads/media/banners/sample_l.webp');
        verify($payload['srcSet']['large'])->equals('/uploads/media/banners/sample_l.webp');
        verify($payload['srcSet']['medium'])->equals('/uploads/media/banners/sample_m.webp');
    }

    public function testResolveDefaultSrcFallsBackToOriginalWhenLargeMissing(): void
    {
        verify(MediaFile::resolveDefaultSrc([
            'original' => '/uploads/media/banners/sample.png',
            'medium' => '/uploads/media/banners/sample_m.webp',
            'mini' => '/uploads/media/banners/sample_s.webp',
        ], 'large'))->equals('/uploads/media/banners/sample.png');
    }

    public function testBuildApiImagePayloadKeepsMediumForCatalog(): void
    {
        $payload = MediaFile::buildApiImagePayload(
            true,
            [
                'original' => '/uploads/media/fabrics/sample.jpg',
                'large' => '/uploads/media/fabrics/sample_l.webp',
                'medium' => '/uploads/media/fabrics/sample_m.webp',
                'mini' => '/uploads/media/fabrics/sample_s.webp',
            ],
            'Sample',
            800,
            600
        );

        verify($payload['src'])->equals('/uploads/media/fabrics/sample_m.webp');
    }
}
