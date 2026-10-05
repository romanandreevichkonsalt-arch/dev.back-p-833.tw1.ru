<?php

namespace tests\unit\services;

use app\models\CatalogDirection;
use app\services\catalog\CatalogService;
use Codeception\Test\Unit;
use Yii;

class CatalogMenuShapeTest extends Unit
{
    protected function _before(): void
    {
        parent::_before();
        Yii::$app->db->open();
    }

    public function testMenuHasExpectedTopLevelKeys(): void
    {
        if (!CatalogDirection::find()->exists()) {
            $this->markTestSkipped('Catalog is not seeded.');
        }

        $menu = (new CatalogService())->getMenu();

        foreach (['directions', 'groups', 'categories', 'collections', 'modelLines', 'menuCollections'] as $key) {
            $this->assertArrayHasKey($key, $menu, 'Missing menu key: ' . $key);
        }

        if ($menu['directions'] !== []) {
            $direction = $menu['directions'][0];
            $this->assertArrayHasKey('categories', $direction);
            $this->assertArrayHasKey('subcategories', $direction);
        }

        if ($menu['groups'] !== []) {
            $group = $menu['groups'][0];
            $this->assertArrayHasKey('id', $group);
            $this->assertArrayHasKey('label', $group);
            $this->assertArrayHasKey('subcategories', $group);
        }

        if ($menu['collections'] !== []) {
            $collection = $menu['collections'][0];
            foreach (['slug', 'label', 'image', 'href'] as $field) {
                $this->assertArrayHasKey($field, $collection, 'Collection missing ' . $field);
            }
        }
    }
}
