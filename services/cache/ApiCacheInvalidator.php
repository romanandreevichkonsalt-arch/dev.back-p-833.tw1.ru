<?php

namespace app\services\cache;

use Yii;

class ApiCacheInvalidator
{
    public static function touch(): void
    {
        Yii::$container->get(ApiResponseCache::class)->bump();
    }
}
