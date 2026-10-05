<?php

namespace tests\unit\services\catalog;

use app\models\CatalogProduct;
use app\services\catalog\CatalogProductPublicVisibility;
use Codeception\Test\Unit;

class CatalogProductPublicVisibilityTest extends Unit
{
    public function testApplyAddsJoinsWithoutSqlError(): void
    {
        $query = CatalogProduct::find()->alias('p')->where(['p.is_active' => true]);
        CatalogProductPublicVisibility::apply($query, 'p');

        verify($query->createCommand()->getRawSql())->stringContainsString('fc_pub');
        verify($query->createCommand()->getRawSql())->stringContainsString('fcol_pub');
        verify($query->createCommand()->getRawSql())->stringContainsString('cm_pub');
    }
}
