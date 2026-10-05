<?php

namespace app\models;

use yii\db\ActiveRecord;

class CatalogModelPriceCategoryLink extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%catalog_model_price_categories}}';
    }

    public function rules(): array
    {
        return [
            [['model_id', 'price_category_id'], 'required'],
            [['model_id', 'price_category_id', 'sort_order'], 'integer'],
            [['created_at'], 'safe'],
            [['model_id'], 'exist', 'targetClass' => CatalogModel::class, 'targetAttribute' => ['model_id' => 'id']],
            [['price_category_id'], 'exist', 'targetClass' => CatalogPriceCategory::class, 'targetAttribute' => ['price_category_id' => 'id']],
            [['price_category_id'], 'unique', 'targetAttribute' => ['model_id', 'price_category_id']],
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
