<?php

namespace app\modules\admin\controllers;

use app\modules\admin\models\LoginForm;
use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\Response;

class SiteController extends Controller
{
    public $layout = 'login';

    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'user' => 'adminUser',
                'rules' => [
                    [
                        'actions' => ['login'],
                        'allow' => true,
                        'roles' => ['?'],
                    ],
                    [
                        'actions' => ['logout'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
        ];
    }

    public function actionLogin()
    {
        if (!Yii::$app->adminUser->isGuest) {
            return $this->redirect(['/admin/dashboard/index']);
        }

        $model = new LoginForm();
        if ($model->load(Yii::$app->request->post()) && $model->login()) {
            return $this->redirect(['/admin/dashboard/index']);
        }

        $model->password = '';

        return $this->render('login', [
            'model' => $model,
        ]);
    }

    public function actionLogout(): Response
    {
        Yii::$app->adminUser->logout();

        return $this->redirect(['/admin/site/login']);
    }
}
