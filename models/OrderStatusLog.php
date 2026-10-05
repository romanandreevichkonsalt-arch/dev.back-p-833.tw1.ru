<?php

namespace app\models;

use yii\db\ActiveRecord;

class OrderStatusLog extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%order_status_log}}';
    }

    public function rules(): array
    {
        return [
            [['order_id', 'new_status', 'created_at'], 'required'],
            [['order_id', 'admin_user_id'], 'integer'],
            [['old_status', 'new_status'], 'string', 'max' => 32],
            [['comment'], 'string'],
            [['created_at'], 'safe'],
        ];
    }

    public function getAdminUser()
    {
        return $this->hasOne(AdminUser::class, ['id' => 'admin_user_id']);
    }

    public function getOldStatusLabel(): string
    {
        if ($this->old_status === null || $this->old_status === '') {
            return '—';
        }

        return Order::statusLabels()[$this->old_status] ?? $this->old_status;
    }

    public function getNewStatusLabel(): string
    {
        return Order::statusLabels()[$this->new_status] ?? $this->new_status;
    }
}
