<?php

namespace app\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\db\Expression;

/**
 * @property int $id
 * @property int $product_id
 * @property string $path
 * @property string|null $alt
 * @property int $sort_order
 * @property bool $is_main
 * @property string $created_at
 *
 * @property Product $product
 */
class ProductImage extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%product_images}}';
    }

    public function behaviors(): array
    {
        return [
            [
                'class' => TimestampBehavior::class,
                'updatedAtAttribute' => false,
                'value' => new Expression('NOW()'),
            ],
        ];
    }

    public function rules(): array
    {
        return [
            [['product_id', 'path'], 'required'],
            [['product_id', 'sort_order'], 'integer'],
            [['is_main'], 'boolean'],
            [['path'], 'string', 'max' => 512],
            [['alt'], 'string', 'max' => 255],
            [
                ['product_id'],
                'exist',
                'targetClass' => Product::class,
                'targetAttribute' => ['product_id' => 'id'],
            ],
        ];
    }

    public function getProduct(): ActiveQuery
    {
        return $this->hasOne(Product::class, ['id' => 'product_id']);
    }

    public function getUrl(): string
    {
        return '/' . ltrim($this->path, '/');
    }
}
