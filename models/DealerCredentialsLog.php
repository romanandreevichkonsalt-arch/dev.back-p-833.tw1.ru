<?php

namespace app\models;

use yii\db\ActiveRecord;

class DealerCredentialsLog extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%dealer_credentials_log}}';
    }

    public function rules(): array
    {
        return [
            [['user_id', 'email', 'created_at'], 'required'],
            [['user_id', 'admin_user_id'], 'integer'],
            [['email'], 'string', 'max' => 255],
            [['is_success'], 'boolean'],
            [['error_message'], 'string'],
            [['created_at'], 'safe'],
        ];
    }

    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public function getAdminUser()
    {
        return $this->hasOne(AdminUser::class, ['id' => 'admin_user_id']);
    }
}
