<?php

namespace app\modules\admin\controllers;

use app\models\AdminUser;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\ForbiddenHttpException;

abstract class BaseController extends Controller
{
    public $layout = 'main';

    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'user' => 'adminUser',
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
        ];
    }

    protected function requirePermission(string $permission): void
    {
        /** @var AdminUser|null $user */
        $user = \Yii::$app->adminUser->identity;
        if ($user === null || !$user->canAccess($permission)) {
            throw new ForbiddenHttpException('Недостаточно прав для этого раздела.');
        }
    }
}
