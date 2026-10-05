<?php

namespace app\modules\admin\controllers;

use yii\web\Response;

class SettingsController extends SettingsBaseController
{
    public function actionIndex(): Response
    {
        return $this->redirect(['/admin/settings-direction/index']);
    }
}
