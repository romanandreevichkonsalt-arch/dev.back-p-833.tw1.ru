<?php

namespace app\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

class SearchFrequentQuery extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%search_frequent_queries}}';
    }

    public function behaviors(): array
    {
        return [
            'timestamp' => [
                'class' => TimestampBehavior::class,
                'value' => static fn (): string => date('Y-m-d H:i:s'),
            ],
        ];
    }

    public function rules(): array
    {
        return [
            [['query'], 'required'],
            [['query'], 'string', 'max' => 255],
            [['sort_order'], 'integer'],
            [['is_active'], 'boolean'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'query' => 'Запрос',
            'sort_order' => 'Порядок',
            'is_active' => 'Активен',
        ];
    }
}
