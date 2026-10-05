<?php

namespace app\models;

use app\models\traits\AutoSlugTrait;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

class CatalogSubcategory extends ActiveRecord
{
    use AutoSlugTrait;

    public static function tableName(): string
    {
        return '{{%catalog_subcategories}}';
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
            [['category_id', 'slug', 'label'], 'required'],
            [['category_id', 'sort_order'], 'integer'],
            [['slug', 'url_slug'], 'string', 'max' => 64],
            [['url_slug'], 'unique'],
            [['label'], 'string', 'max' => 255],
            [['is_active'], 'boolean'],
            [['slug'], 'unique', 'targetAttribute' => ['category_id', 'slug']],
            [['category_id'], 'exist', 'targetClass' => CatalogCategory::class, 'targetAttribute' => ['category_id' => 'id']],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'category_id' => 'Категория',
            'slug' => 'Slug',
            'label' => 'Название',
            'sort_order' => 'Порядок',
            'is_active' => 'Активна',
        ];
    }

    public function getCategory()
    {
        return $this->hasOne(CatalogCategory::class, ['id' => 'category_id']);
    }

    public function getProducts()
    {
        return $this->hasMany(CatalogProduct::class, ['subcategory_id' => 'id']);
    }
}
