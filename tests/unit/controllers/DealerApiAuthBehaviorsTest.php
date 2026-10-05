<?php

namespace tests\unit\controllers;

use app\controllers\api\v1\DealerAuthController;
use app\controllers\api\v1\DealerBonusesController;
use app\controllers\api\v1\DealerProfileController;
use app\controllers\api\v1\PingController;
use Yii;
use yii\filters\auth\HttpBearerAuth;

class DealerApiAuthBehaviorsTest extends \Codeception\Test\Unit
{
    public function testDealerProfileRequiresBearerOnIndex(): void
    {
        $behaviors = (new DealerProfileController('dealer-profile', Yii::$app))->behaviors();

        verify($behaviors['authenticator']['class'])->equals(HttpBearerAuth::class);
        verify($behaviors['authenticator']['except'])->equals(['options']);
    }

    public function testDealerBonusesRequiresBearerOnIndex(): void
    {
        $behaviors = (new DealerBonusesController('dealer-bonuses', Yii::$app))->behaviors();

        verify($behaviors['authenticator']['class'])->equals(HttpBearerAuth::class);
        verify($behaviors['authenticator']['except'])->equals(['options']);
    }

    public function testDealerAuthLoginIsPublic(): void
    {
        $behaviors = (new DealerAuthController('dealer-auth', Yii::$app))->behaviors();

        verify($behaviors['authenticator']['except'])->equals(['login', 'options']);
    }

    public function testPingIndexIsPublic(): void
    {
        $behaviors = (new PingController('ping', Yii::$app))->behaviors();

        verify($behaviors['authenticator']['except'])->equals(['index', 'options']);
    }
}
