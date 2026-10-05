<?php

namespace tests\unit\services;

use app\models\MediaFile;
use app\services\cache\ApiResponseCache;
use app\services\media\MediaUrlResolver;
use Codeception\Test\Unit;
use yii\caching\ArrayCache;

class MediaApiPayloadTest extends Unit
{
    public function testBuildListingApiImagePayloadUsesMediumAsDefaultSrc(): void
    {
        $payload = MediaFile::buildApiImagePayload(
            true,
            [
                'original' => '/uploads/media/fabrics/2026/08/sample.jpg',
                'large' => '/uploads/media/fabrics/2026/08/sample_l.webp',
                'medium' => '/uploads/media/fabrics/2026/08/sample_m.webp',
                'mini' => '/uploads/media/fabrics/2026/08/sample_s.webp',
            ],
            'Sample',
            800,
            600,
            null,
            'medium',
            true
        );

        verify($payload['src'])->equals('/uploads/media/fabrics/2026/08/sample_m.webp');
        verify($payload['srcSet']['mini'])->equals('/uploads/media/fabrics/2026/08/sample_s.webp');
        verify($payload['srcSet']['medium'])->equals('/uploads/media/fabrics/2026/08/sample_m.webp');
        verify($payload['srcSet']['large'] ?? null)->null();
        verify($payload['srcSet']['original'] ?? null)->null();
    }

    public function testBuildApiImagePayloadUsesMediumAsDefaultSrc(): void
    {
        $payload = MediaFile::buildApiImagePayload(
            true,
            [
                'original' => '/uploads/media/fabrics/2026/08/sample.jpg',
                'large' => '/uploads/media/fabrics/2026/08/sample_l.webp',
                'medium' => '/uploads/media/fabrics/2026/08/sample_m.webp',
                'mini' => '/uploads/media/fabrics/2026/08/sample_s.webp',
            ],
            'Sample',
            800,
            600
        );

        verify($payload['src'])->equals('/uploads/media/fabrics/2026/08/sample_m.webp');
        verify($payload['srcSet']['mini'])->equals('/uploads/media/fabrics/2026/08/sample_s.webp');
        verify($payload['srcSet']['large'])->equals('/uploads/media/fabrics/2026/08/sample_l.webp');
        verify($payload['srcSet']['original'])->equals('/uploads/media/fabrics/2026/08/sample.jpg');
        verify($payload['alt'])->equals('Sample');
        verify($payload['width'])->equals(800);
        verify($payload['height'])->equals(600);
    }

    public function testResolveTreeExpandsMediaIdToPayload(): void
    {
        $resolver = new MediaUrlResolver();
        $payloadCache = new \ReflectionProperty(MediaUrlResolver::class, 'imagePayloadCache');
        $payloadCache->setAccessible(true);
        $payloadCache->setValue($resolver, [
            13 => [
                'src' => '/uploads/media/preview_m.webp',
                'srcSet' => [
                    'mini' => '/uploads/media/preview_s.webp',
                    'medium' => '/uploads/media/preview_m.webp',
                    'original' => '/uploads/media/preview.webp',
                ],
                'alt' => 'Preview',
            ],
        ]);
        $urlCache = new \ReflectionProperty(MediaUrlResolver::class, 'urlCache');
        $urlCache->setAccessible(true);
        $urlCache->setValue($resolver, [
            13 => '/uploads/media/preview_m.webp',
        ]);

        $payload = $resolver->resolveTree([
            'items' => [
                [
                    'image' => [
                        'src' => '13',
                        'alt' => 'Card',
                    ],
                ],
            ],
        ]);

        verify($payload['items'][0]['image']['src'])->equals('/uploads/media/preview_m.webp');
        verify($payload['items'][0]['image']['srcSet']['mini'])->equals('/uploads/media/preview_s.webp');
        verify($payload['items'][0]['image']['alt'])->equals('Card');
    }
}
