<?php

namespace app\models;

use yii\db\ActiveRecord;

class UserProfile extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%user_profiles}}';
    }

    public function rules(): array
    {
        return [
            [['user_id'], 'required'],
            [['user_id'], 'integer'],
            [['raw_payload'], 'string'],
            [['created_at', 'updated_at'], 'safe'],
            [['user_id'], 'unique'],
            [['first_name', 'last_name', 'display_name', 'email', 'yandex_login'], 'string', 'max' => 255],
            [['avatar_url'], 'string', 'max' => 1024],
        ];
    }
}
