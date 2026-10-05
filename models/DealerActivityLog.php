<?php

namespace app\models;

use yii\db\ActiveRecord;

class DealerActivityLog extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%dealer_activity_logs}}';
    }

    public function rules(): array
    {
        return [
            [['user_id', 'action', 'created_at'], 'required'],
            [['user_id'], 'integer'],
            [['action'], 'string', 'max' => 64],
            [['context'], 'string'],
            [['ip'], 'string', 'max' => 45],
            [['user_agent'], 'string', 'max' => 512],
            [['created_at'], 'safe'],
        ];
    }

    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public static function actionLabels(): array
    {
        return [
            'auth.login' => 'Вход',
            'auth.logout' => 'Выход',
            'price.view' => 'Просмотр прайса',
            'cart.add' => 'Добавление в корзину',
            'order.create' => 'Оформление заказа',
            'promo.apply' => 'Применение промокода',
            'cashback.apply' => 'Применение кэшбека',
        ];
    }

    public function getActionLabel(): string
    {
        return self::actionLabels()[$this->action] ?? $this->action;
    }
}
