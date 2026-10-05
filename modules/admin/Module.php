<?php

namespace app\modules\admin;

use Yii;

class Module extends \yii\base\Module
{
    public $controllerNamespace = 'app\modules\admin\controllers';

    public $defaultRoute = 'dashboard';

    public $layout = 'main';

    public function init(): void
    {
        parent::init();

        Yii::$app->language = 'ru-RU';
        $this->layoutPath = '@app/modules/admin/views/layouts';
    }
}
