<?php

namespace app\models;

use app\models\traits\AutoSlugTrait;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

class CatalogLayout extends ActiveRecord
{
    use AutoSlugTrait;
    public static function tableName(): string
    {
        return '{{%catalog_layouts}}';
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
            [['slug'], 'string', 'max' => 64],
            [['slug'], 'unique'],
            [['label'], 'string', 'max' => 255],
            [['description'], 'string'],
            [['preview_image_id', 'sort_order'], 'integer'],
            [['is_active'], 'boolean'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'slug' => 'Slug',
            'label' => 'Название',
            'description' => 'Описание',
            'preview_image_id' => 'Превью',
            'sort_order' => 'Порядок',
            'is_active' => 'Активна',
        ];
    }

    public function getPreviewImage()
    {
        return $this->hasOne(MediaFile::class, ['id' => 'preview_image_id']);
    }

    public function getProducts()
    {
        return $this->hasMany(CatalogProduct::class, ['layout_id' => 'id']);
    }
}
