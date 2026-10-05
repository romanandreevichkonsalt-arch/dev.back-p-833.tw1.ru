<?php

namespace app\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

class CatalogModelPrice extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%catalog_model_prices}}';
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
            [['model_id', 'price_category_id'], 'required'],
            [['model_id', 'price_category_id'], 'integer'],
            [['price_display'], 'required'],
            [['price_display'], 'string', 'max' => 64],
            [['model_id'], 'exist', 'targetClass' => CatalogModel::class, 'targetAttribute' => ['model_id' => 'id']],
            [['price_category_id'], 'exist', 'targetClass' => CatalogPriceCategory::class, 'targetAttribute' => ['price_category_id' => 'id']],
            [['price_category_id'], 'unique', 'targetAttribute' => ['model_id', 'price_category_id']],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'price_category_id' => 'Ценовая категория',
            'price_display' => 'Цена (отображение)',
        ];
    }

    public function getModel()
    {
        return $this->hasOne(CatalogModel::class, ['id' => 'model_id']);
    }

    public function getPriceCategory()
    {
        return $this->hasOne(CatalogPriceCategory::class, ['id' => 'price_category_id']);
    }
}
