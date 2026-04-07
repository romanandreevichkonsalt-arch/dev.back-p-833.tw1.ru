<?php

namespace app\controllers;

use OpenApi\Annotations as OA;
use OpenApi\Generator;
use Yii;
use yii\web\Controller;

class SwaggerController extends Controller
{
    public function actions(): array
    {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
        ];
    }

    /**
     * @OA\Get(
     *     path="/swagger/json-schema",
     *     tags={"Documentation"},
     *     summary="Returns generated OpenAPI schema as JSON",
     *     @OA\Response(
     *         response=200,
     *         description="OpenAPI schema",
     *         @OA\JsonContent(type="object")
     *     )
     * )
     */
    public function actionJsonSchema(): string
    {
        header('Access-Control-Allow-Origin: *');

        $openapi = (new Generator())->generate(
            [
                Yii::getAlias('@app/docs'),
                Yii::getAlias('@app/controllers/api/v1'),
                Yii::getAlias('@app/models'),
            ],
            null,
            false
        );

        return $openapi ? $openapi->toJson() : '{}';
    }
}
