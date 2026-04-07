<?php

namespace app\modules\admin\controllers;

use OpenApi\Annotations as OA;
use yii\filters\AccessControl;
use yii\web\Controller;

class DefaultController extends Controller
{
    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
        ];
    }

    /**
     * @OA\Get(
     *     path="/admin/default/index",
     *     tags={"Админка"},
     *     summary="Стартовая страница модуля администрирования",
     *     @OA\Response(
     *         response=200,
     *         description="HTML-страница админки"
     *     )
     * )
     */
    public function actionIndex(): string
    {
        return $this->render('index');
    }
}
