<?php

namespace tests\unit\services;

use app\models\CatalogSubcategory;
use app\services\catalog\CatalogCategoryDuplicateMergeService;
use Codeception\Test\Unit;
use ReflectionClass;

class CatalogCategoryDuplicateMergeServiceTest extends Unit
{
    public function testSubcategoryKeysMatchEquivalentSlugs(): void
    {
        $service = new CatalogCategoryDuplicateMergeService();
        $method = (new ReflectionClass($service))->getMethod('resolveSubcategoryKey');
        $method->setAccessible(true);

        $straight = new CatalogSubcategory(['slug' => 'straight', 'label' => 'Прямой диван', 'url_slug' => 'pryamye']);
        $imported = new CatalogSubcategory(['slug' => 'pryamoy-divan', 'label' => 'Прямой диван']);

        verify($method->invoke($service, $straight))->equals('group:straight');
        verify($method->invoke($service, $imported))->equals('group:straight');
    }
}
