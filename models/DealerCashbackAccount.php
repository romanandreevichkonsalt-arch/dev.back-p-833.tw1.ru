<?php

namespace app\models;

use yii\db\ActiveRecord;

class DealerCashbackAccount extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%dealer_cashback_accounts}}';
    }

    public function rules(): array
    {
        return [
            [['user_id', 'period_year_month', 'updated_at'], 'required'],
            [['user_id'], 'integer'],
            [['balance', 'period_total'], 'number'],
            [['period_year_month'], 'string', 'max' => 7],
            [['updated_at'], 'safe'],
        ];
    }

    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }
}
