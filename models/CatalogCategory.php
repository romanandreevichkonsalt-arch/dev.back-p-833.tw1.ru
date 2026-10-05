<?php

namespace app\models;

use app\models\traits\AutoSlugTrait;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

class CatalogCategory extends ActiveRecord
{
    use AutoSlugTrait;

    public static function tableName(): string
    {
        return '{{%catalog_categories}}';
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
            [['slug', 'label'], 'required'],
            [['sort_order'], 'integer'],
            [['slug', 'url_slug'], 'string', 'max' => 64],
            [['slug'], 'unique'],
            [['url_slug'], 'unique'],
            [['label'], 'string', 'max' => 255],
            [['is_active'], 'boolean'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'slug' => 'Slug',
            'label' => 'Название',
            'sort_order' => 'Порядок',
            'is_active' => 'Активна',
        ];
    }

    public function getSubcategories()
    {
        return $this->hasMany(CatalogSubcategory::class, ['category_id' => 'id'])
            ->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC]);
    }

    public function getProducts()
    {
        return $this->hasMany(CatalogProduct::class, ['subcategory_id' => 'id'])
            ->via('subcategories');
    }

    /**
     * @return self[]
     */
    public static function findActiveOrdered(): array
    {
        return self::find()
            ->where(['is_active' => true])
            ->orderBy(['sort_order' => SORT_ASC, 'label' => SORT_ASC])
            ->all();
    }
}
