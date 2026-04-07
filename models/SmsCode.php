<?php

namespace app\models;

use yii\db\ActiveRecord;

class SmsCode extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%sms_codes}}';
    }

    public function rules(): array
    {
        return [
            [['user_id', 'phone', 'code', 'expires_at'], 'required'],
            [['user_id'], 'integer'],
            [['used_at', 'created_at'], 'safe'],
            [['phone'], 'string', 'max' => 11],
            [['phone'], 'match', 'pattern' => '/^7\d{10}$/'],
            [['code'], 'string', 'max' => 6],
            [['expires_at'], 'datetime', 'format' => 'php:Y-m-d H:i:s'],
        ];
    }
}
