<?php

namespace app\controllers\api\v1;

use OpenApi\Annotations as OA;
use Yii;
use yii\filters\Cors;
use yii\web\Controller;
use yii\web\Response;

class OptionsController extends Controller
{
    public $enableCsrfValidation = false;

    public function beforeAction($action): bool
    {
        Yii::$app->user->enableSession = false;

        return parent::beforeAction($action);
    }

    public function behaviors(): array
    {
        $behaviors = parent::behaviors();

        $behaviors['corsFilter'] = [
            'class' => Cors::class,
            'cors' => [
                'Origin' => ['*'],
                'Access-Control-Request-Method' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
                'Access-Control-Request-Headers' => ['*'],
                'Access-Control-Allow-Credentials' => false,
            ],
        ];

        return $behaviors;
    }

    /**
     * @OA\Options(
     *     path="/api/v1/{path}",
     *     tags={"Система"},
     *     summary="Preflight-обработчик для всех OPTIONS запросов API v1",
     *     @OA\Parameter(
     *         name="path",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="OPTIONS обработан успешно"
     *     )
     * )
     */
    public function actionPreflight(): array
    {
        Yii::$app->response->statusCode = 200;
        Yii::$app->response->format = Response::FORMAT_JSON;

        return ['status' => 'ok'];
    }
}
