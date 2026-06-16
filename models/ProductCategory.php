<?php

namespace app\models;

use yii\db\ActiveRecord;

/**
 * @property int $product_id
 * @property int $category_id
 */
class ProductCategory extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%product_categories}}';
    }

    public static function primaryKey(): array
    {
        return ['product_id', 'category_id'];
    }

    public function rules(): array
    {
        return [
            [['product_id', 'category_id'], 'required'],
            [['product_id', 'category_id'], 'integer'],
            [
                ['product_id'],
                'exist',
                'targetClass' => Product::class,
                'targetAttribute' => ['product_id' => 'id'],
            ],
            [
                ['category_id'],
                'exist',
                'targetClass' => Category::class,
                'targetAttribute' => ['category_id' => 'id'],
            ],
        ];
    }
}
