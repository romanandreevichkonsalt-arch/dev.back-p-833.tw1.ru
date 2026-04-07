<?php

namespace app\models;

use yii\db\ActiveRecord;

class ApiAccessToken extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%api_access_tokens}}';
    }

    public function rules(): array
    {
        return [
            [['user_id', 'token_hash', 'expires_at'], 'required'],
            [['user_id'], 'integer'],
            [['created_at', 'expires_at', 'revoked_at'], 'safe'],
            [['token_hash'], 'string', 'max' => 64],
            [['token_hash'], 'unique'],
        ];
    }
}
