<?php

namespace tests\unit\services;

use app\services\cache\ApiResponseCache;
use Codeception\Test\Unit;
use Yii;
use yii\caching\ArrayCache;
use yii\console\Application;

class ApiResponseCacheTest extends Unit
{
    protected function _before(): void
    {
        if (Yii::$app === null) {
            new Application(require dirname(__DIR__, 3) . '/config/test.php');
        }

        Yii::$app->set('cache', new ArrayCache());
    }

    public function testBumpInvalidatesPreviousEntries(): void
    {
        $cache = new ApiResponseCache();
        $cache->bump();
        $first = $cache->get('test', 'key', static fn (): string => 'value-1', 60);
        $cache->bump();
        $second = $cache->get('test', 'key', static fn (): string => 'value-2', 60);

        verify($first)->equals('value-1');
        verify($second)->equals('value-2');
    }
}
