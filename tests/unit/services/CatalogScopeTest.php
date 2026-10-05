<?php

namespace tests\unit\services;

use app\services\catalog\CatalogScope;
use Codeception\Test\Unit;
use yii\web\BadRequestHttpException;

class CatalogScopeTest extends Unit
{
    public function testFromParamsRequiresScopeByDefault(): void
    {
        $this->expectException(BadRequestHttpException::class);
        CatalogScope::fromParams([]);
    }

    public function testFromParamsAllowsEmptyScopeWhenNotRequired(): void
    {
        $scope = CatalogScope::fromParams([], false);

        verify($scope->scopeMode)->equals('all');
        verify($scope->direction)->null();
        verify($scope->subcategory)->null();
        verify($scope->appliedSlugs())->equals([
            'direction' => null,
            'collection' => null,
            'modelLine' => null,
            'category' => null,
            'subcategory' => null,
        ]);
    }
}
