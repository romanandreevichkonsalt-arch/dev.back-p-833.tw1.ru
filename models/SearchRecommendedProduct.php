<?php

namespace app\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

class SearchRecommendedProduct extends ActiveRecord
{
    public const SCOPE_MAIN = 'main';
    public const SCOPE_DIRECTION = 'direction';
    public const SCOPE_CATEGORY = 'category';
    public const SCOPE_SUBCATEGORY = 'subcategory';

    public const MAIN_SLOT_COUNT = 3;
    public const SCOPED_SLOT_COUNT = 2;

    public static function tableName(): string
    {
        return '{{%search_recommended_products}}';
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
            [['scope_type', 'slot', 'catalog_product_id'], 'required'],
            [['scope_id', 'slot', 'catalog_product_id'], 'integer'],
            [['scope_type'], 'string', 'max' => 32],
            [['scope_type'], 'in', 'range' => [
                self::SCOPE_MAIN,
                self::SCOPE_DIRECTION,
                self::SCOPE_CATEGORY,
                self::SCOPE_SUBCATEGORY,
            ]],
            [['catalog_product_id'], 'exist', 'targetClass' => CatalogProduct::class, 'targetAttribute' => ['catalog_product_id' => 'id']],
        ];
    }

    public function getCatalogProduct()
    {
        return $this->hasOne(CatalogProduct::class, ['id' => 'catalog_product_id']);
    }
}
