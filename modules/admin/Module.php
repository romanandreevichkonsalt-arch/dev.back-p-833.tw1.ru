<?php

namespace app\modules\admin;

use Yii;

class Module extends \yii\base\Module
{
    public $controllerNamespace = 'app\modules\admin\controllers';

    public $defaultRoute = 'default/index';

    public $layout = 'main';

    public function init(): void
    {
        parent::init();
        Yii::$app->user->loginUrl = ['/admin/auth/login'];
    }
}
