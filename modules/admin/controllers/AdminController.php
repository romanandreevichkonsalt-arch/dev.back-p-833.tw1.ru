<?php

namespace app\modules\admin\controllers;

use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\ForbiddenHttpException;

abstract class AdminController extends Controller
{
    public $layout = 'main';

    protected function permissionAccess(string $permission): array
    {
        return [
            'class' => AccessControl::class,
            'rules' => [
                ['allow' => true, 'roles' => [$permission]],
            ],
        ];
    }

    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        if (Yii::$app->user->isGuest) {
            Yii::$app->user->loginRequired();

            return false;
        }

        $user = Yii::$app->user->identity;
        if ($user === null || !$user->hasAdminAccess()) {
            throw new ForbiddenHttpException('Доступ в админку запрещён.');
        }

        return true;
    }

    protected function flashSuccess(string $message): void
    {
        Yii::$app->session->setFlash('success', $message);
    }

    protected function flashError(string $message): void
    {
        Yii::$app->session->setFlash('error', $message);
    }
}
