<?php

namespace app\models;

use yii\db\ActiveRecord;

class OrderDocument extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%order_documents}}';
    }

    public function rules(): array
    {
        return [
            [['order_id', 'label', 'stored_path'], 'required'],
            [['order_id', 'sort_order'], 'integer'],
            [['label'], 'string', 'max' => 255],
            [['stored_path'], 'string', 'max' => 512],
            [['original_name'], 'string', 'max' => 255],
            [['created_at'], 'safe'],
        ];
    }

    public function getOrder()
    {
        return $this->hasOne(Order::class, ['id' => 'order_id']);
    }
}
