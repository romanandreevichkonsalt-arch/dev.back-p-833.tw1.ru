<?php

namespace tests\unit\controllers;

use app\controllers\api\v1\MoodboardController;
use Codeception\Test\Unit;
use Yii;

class MoodboardApiAuthBehaviorsTest extends Unit
{
    public function testOptionalBearerOnPublicAndGuestActions(): void
    {
        $behaviors = (new MoodboardController('moodboard', Yii::$app))->behaviors();
        verify($behaviors['authenticator']['class'])->equals(\yii\filters\auth\HttpBearerAuth::class);
        $optional = $behaviors['authenticator']['optional'] ?? [];
        verify(in_array('view-public-board', $optional, true))->true();
        verify(in_array('picker-bootstrap', $optional, true))->true();
        verify(in_array('create-board', $optional, true))->true();
        verify(in_array('sync-boards', $optional, true))->false();
    }
}
