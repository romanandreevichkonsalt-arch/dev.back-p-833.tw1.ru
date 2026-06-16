<?php

namespace app\modules\admin\controllers;

use app\models\ApiAccessToken;
use app\models\AttributeDefinition;
use app\models\Category;
use app\models\Product;
use app\models\User;

class DefaultController extends AdminController
{
    public function actionIndex(): string
    {
        return $this->render('index', [
            'stats' => [
                'Категории' => Category::find()->count(),
                'Товары' => Product::find()->count(),
                'Атрибуты' => AttributeDefinition::find()->count(),
                'Пользователи' => User::find()->count(),
                'Активные токены' => ApiAccessToken::find()->where(['revoked_at' => null])->count(),
            ],
        ]);
    }
}
