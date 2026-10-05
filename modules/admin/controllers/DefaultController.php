<?php

namespace app\modules\admin\controllers;

use yii\web\Response;

class DefaultController extends BaseController
{
    public function actionIndex(): Response
    {
        return $this->redirect(['/admin/dashboard/index']);
    }
}
