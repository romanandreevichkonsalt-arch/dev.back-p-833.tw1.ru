<?php

namespace tests\unit\services;

use app\services\moodboard\MoodboardShareUrls;
use Codeception\Test\Unit;
use Yii;

class MoodboardShareUrlsTest extends Unit
{
    public function testPublicApiPath(): void
    {
        verify(MoodboardShareUrls::publicApiPath('abc123'))->equals('/api/v1/moodboard/public/abc123');
    }

    public function testFrontendShareUrl(): void
    {
        Yii::$app->params['frontendUrl'] = 'https://front.example';
        Yii::$app->params['moodboardSharePathPrefix'] = '/moodboard/public/';

        verify(MoodboardShareUrls::frontendShareUrl('deadbeef'))
            ->equals('https://front.example/moodboard/public/deadbeef');
        verify(MoodboardShareUrls::frontendShareUrl(null))->null();
    }
}
