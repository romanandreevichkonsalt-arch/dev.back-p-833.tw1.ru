<?php

namespace app\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

class SearchCatalogPriorityModel extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%search_catalog_priority_models}}';
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
            [['direction_id', 'catalog_model_id'], 'required'],
            [['direction_id', 'catalog_model_id', 'sample_catalog_product_id', 'sort_order'], 'integer'],
            [['direction_id'], 'exist', 'targetClass' => CatalogDirection::class, 'targetAttribute' => ['direction_id' => 'id']],
            [['catalog_model_id'], 'exist', 'targetClass' => CatalogModel::class, 'targetAttribute' => ['catalog_model_id' => 'id']],
        ];
    }

    public function getCatalogModel()
    {
        return $this->hasOne(CatalogModel::class, ['id' => 'catalog_model_id']);
    }

    public function getSampleCatalogProduct()
    {
        return $this->hasOne(CatalogProduct::class, ['id' => 'sample_catalog_product_id']);
    }

    public function getDirection()
    {
        return $this->hasOne(CatalogDirection::class, ['id' => 'direction_id']);
    }
}
