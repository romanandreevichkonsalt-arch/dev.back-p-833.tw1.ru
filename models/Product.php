<?php

namespace app\models;

use app\behaviors\SoftDeleteBehavior;
use app\models\traits\SoftDeleteTrait;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\db\Expression;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $sku
 * @property string|null $description
 * @property string $price
 * @property string|null $old_price
 * @property bool $is_active
 * @property int $stock_qty
 * @property string|null $seo_title
 * @property string|null $seo_description
 * @property string|null $seo_h1
 * @property string $created_at
 * @property string $updated_at
 * @property string|null $deleted_at
 *
 * @property Category[] $categories
 * @property ProductAttribute[] $productAttributes
 * @property ProductImage[] $images
 */
class Product extends ActiveRecord
{
    use SoftDeleteTrait;

    public static function tableName(): string
    {
        return '{{%products}}';
    }

    public function behaviors(): array
    {
        return [
            [
                'class' => TimestampBehavior::class,
                'value' => new Expression('NOW()'),
            ],
            SoftDeleteBehavior::class,
        ];
    }

    public function rules(): array
    {
        return [
            [['name', 'slug', 'sku'], 'required'],
            [['description'], 'string'],
            [['price'], 'number', 'min' => 0],
            [['old_price'], 'number', 'min' => 0],
            [['stock_qty'], 'integer', 'min' => 0],
            [['is_active'], 'boolean'],
            [['name', 'slug', 'seo_title', 'seo_h1'], 'string', 'max' => 255],
            [['seo_description'], 'string', 'max' => 512],
            [['sku'], 'string', 'max' => 64],
            [['slug', 'sku'], 'unique'],
            [['slug'], 'match', 'pattern' => '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'message' => 'Slug должен быть в формате kebab-case.'],
        ];
    }

    public function getCategories(): ActiveQuery
    {
        return $this->hasMany(Category::class, ['id' => 'category_id'])
            ->viaTable('{{%product_categories}}', ['product_id' => 'id']);
    }

    public function getProductAttributes(): ActiveQuery
    {
        return $this->hasMany(ProductAttribute::class, ['product_id' => 'id']);
    }

    public function getImages(): ActiveQuery
    {
        return $this->hasMany(ProductImage::class, ['product_id' => 'id'])
            ->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC]);
    }

    public function getMainImage(): ActiveQuery
    {
        return $this->hasOne(ProductImage::class, ['product_id' => 'id'])
            ->andWhere(['is_main' => true]);
    }

    public function toApiArray(bool $detailed = true): array
    {
        $data = [
            'id' => (int) $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'sku' => $this->sku,
            'description' => $this->description,
            'price' => (float) $this->price,
            'old_price' => $this->old_price !== null ? (float) $this->old_price : null,
            'is_active' => (bool) $this->is_active,
            'stock_qty' => (int) $this->stock_qty,
            'seo_title' => $this->seo_title,
            'seo_description' => $this->seo_description,
            'seo_h1' => $this->seo_h1,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];

        if ($detailed) {
            $data['categories'] = array_map(
                static fn (Category $category): array => [
                    'id' => (int) $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                ],
                $this->categories
            );
            $data['attributes'] = array_map(
                static fn (ProductAttribute $attribute): array => $attribute->toApiArray(),
                $this->productAttributes
            );
            $data['images'] = array_map(
                static fn (ProductImage $image): array => [
                    'id' => (int) $image->id,
                    'url' => $image->getUrl(),
                    'alt' => $image->alt,
                    'is_main' => (bool) $image->is_main,
                    'sort_order' => (int) $image->sort_order,
                ],
                $this->images
            );
        }

        return $data;
    }
}
