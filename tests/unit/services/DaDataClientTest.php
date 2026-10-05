<?php

namespace tests\unit\services;

use app\components\dadata\DaDataClient;
use Codeception\Test\Unit;
use Yii;

class DaDataClientTest extends Unit
{
    protected function _before(): void
    {
        parent::_before();
        Yii::$app->params['dadataApiKey'] = '';
    }

    public function testSuggestCityReturnsMockWhenNoApiKey(): void
    {
        $rows = (new DaDataClient())->suggestCity('Краснодар', 5);

        $this->assertNotEmpty($rows);
        $this->assertStringContainsString('Краснодар', $rows[0]['value']);
    }

    public function testSuggestFullAddressReturnsMockWhenNoApiKey(): void
    {
        $rows = (new DaDataClient())->suggestFullAddress('Москва Тверская', 3);

        $this->assertNotEmpty($rows);
        $this->assertArrayHasKey('data', $rows[0]);
    }
}
