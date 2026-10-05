<?php

namespace app\models;

use app\models\traits\AutoSlugTrait;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

class CatalogBadge extends ActiveRecord
{
    use AutoSlugTrait;
    public const VARIANT_HIT = 'hit';
    public const VARIANT_NEW = 'new';

    public static function tableName(): string
    {
        return '{{%catalog_badges}}';
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
            [['variant'], 'string', 'max' => 32],
            [['variant'], 'in', 'range' => array_keys(self::variantLabels())],
            [['image_id', 'sort_order'], 'integer'],
            [['is_active'], 'boolean'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'slug' => 'Slug',
            'label' => 'Текст',
            'variant' => 'Вариант',
            'image_id' => 'Изображение',
            'sort_order' => 'Порядок',
            'is_active' => 'Активен',
        ];
    }

    public static function variantLabels(): array
    {
        return [
            self::VARIANT_HIT => 'Хит продаж',
            self::VARIANT_NEW => 'Новинка',
        ];
    }

    public function getImage()
    {
        return $this->hasOne(MediaFile::class, ['id' => 'image_id']);
    }

    public function getProducts()
    {
        return $this->hasMany(CatalogProduct::class, ['badge_id' => 'id']);
    }
}
