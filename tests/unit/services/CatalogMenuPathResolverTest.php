<?php

namespace tests\unit\services;

use app\services\catalog\CatalogMenuPathResolver;
use Codeception\Test\Unit;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;

class CatalogMenuPathResolverTest extends Unit
{
    public function testSplitPathIgnoresEmptySegments(): void
    {
        $resolver = new CatalogMenuPathResolver();
        verify($resolver->splitPath('/a-plus/divany/'))->equals(['a-plus', 'divany']);
        verify($resolver->splitPath('pryamoy-divan'))->equals(['pryamoy-divan']);
    }

    public function testEmptyPathThrowsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);
        (new CatalogMenuPathResolver())->toParams('///');
    }

    public function testTooManySegmentsThrowsBadRequest(): void
    {
        $this->expectException(BadRequestHttpException::class);
        (new CatalogMenuPathResolver())->toParams('a/b/c/d/e');
    }
}
