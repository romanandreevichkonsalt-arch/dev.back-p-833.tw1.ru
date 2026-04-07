<?php

namespace app\controllers;

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

    public function actionJsonSchema(): string
    {
        header('Access-Control-Allow-Origin: *');

        $openapi = (new Generator())->generate(
            [
                Yii::getAlias('@app/docs'),
                Yii::getAlias('@app/controllers'),
                Yii::getAlias('@app/models'),
            ],
            null,
            false
        );

        return $openapi ? $openapi->toJson() : '{}';
    }
}
