<?php

namespace tests\unit\services;

use app\models\MediaFile;
use app\services\media\MediaUrlResolver;
use Codeception\Test\Unit;

class MediaUrlResolverTest extends Unit
{
    public function testResolveKeepsAbsoluteAndRelativeUrls(): void
    {
        $resolver = new MediaUrlResolver();

        verify($resolver->resolve('https://example.com/a.webp'))->equals('https://example.com/a.webp');
        verify($resolver->resolve('/uploads/media/a.webp'))->equals('/uploads/media/a.webp');
    }

    public function testResolveUsesMediumUrlForMediaId(): void
    {
        $resolver = new MediaUrlResolver();
        $urlCache = new \ReflectionProperty(MediaUrlResolver::class, 'urlCache');
        $urlCache->setAccessible(true);
        $urlCache->setValue($resolver, [
            13 => '/uploads/media/preview_m.webp',
        ]);

        verify($resolver->resolve('13'))->equals('/uploads/media/preview_m.webp');
    }

    public function testPageResolverUsesLargeUrlForVariantPath(): void
    {
        $media = MediaFile::find()
            ->where(['not', ['path_medium' => null]])
            ->orderBy(['id' => SORT_DESC])
            ->one();

        if ($media === null) {
            $this->markTestSkipped('No media files in database.');
        }

        $resolver = MediaUrlResolver::forPageContent();
        $payload = $resolver->resolveImagePayload($media->getPublicUrl('medium'), 'Test');

        verify($payload)->notNull();
        verify($payload['srcSet']['original'] ?? null)->notNull();
        verify($payload['src'])->notEquals($media->getPublicUrl('mini'));
        verify(in_array($payload['src'], [
            $media->getPublicUrl('large'),
            $media->getPublicUrl('original'),
        ], true))->true();
    }
}
