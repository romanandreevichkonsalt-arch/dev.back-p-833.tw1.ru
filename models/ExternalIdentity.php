<?php

namespace app\models;

use yii\db\ActiveRecord;

class ExternalIdentity extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%external_identities}}';
    }

    public function rules(): array
    {
        return [
            [['user_id', 'provider', 'provider_user_id'], 'required'],
            [['user_id'], 'integer'],
            [['raw_payload'], 'string'],
            [['created_at', 'updated_at'], 'safe'],
            [['provider'], 'string', 'max' => 32],
            [['provider_user_id', 'email'], 'string', 'max' => 255],
            [['provider', 'provider_user_id'], 'unique', 'targetAttribute' => ['provider', 'provider_user_id']],
        ];
    }
}
