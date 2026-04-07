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
     *     tags={"Admin"},
     *     summary="Admin module landing page",
     *     @OA\Response(
     *         response=200,
     *         description="HTML admin page"
     *     )
     * )
     */
    public function actionIndex(): string
    {
        return $this->render('index');
    }
}
