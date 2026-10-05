<?php

namespace app\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

class CatalogPromotion extends ActiveRecord
{
    public const DISCOUNT_PERCENT = 'percent';
    public const DISCOUNT_FIXED = 'fixed_amount';

    public const SCOPE_MODEL = 'model';
    public const SCOPE_PRODUCT = 'product';

    public static function tableName(): string
    {
        return '{{%catalog_promotions}}';
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
            [['title', 'discount_type', 'discount_value', 'starts_at', 'ends_at', 'scope_type', 'catalog_model_id'], 'required'],
            [['title'], 'string', 'max' => 255],
            [['discount_type'], 'in', 'range' => [self::DISCOUNT_PERCENT, self::DISCOUNT_FIXED]],
            [['scope_type'], 'in', 'range' => [self::SCOPE_MODEL, self::SCOPE_PRODUCT]],
            [['discount_value'], 'number', 'min' => 0],
            [['catalog_model_id', 'catalog_product_id', 'image_media_id'], 'integer'],
            [['starts_at', 'ends_at'], 'safe'],
            ['catalog_product_id', 'required', 'when' => static fn (self $m): bool => $m->scope_type === self::SCOPE_PRODUCT],
            ['discount_value', 'validateDiscountValue'],
        ];
    }

    public function validateDiscountValue(): void
    {
        if ($this->discount_type === self::DISCOUNT_PERCENT) {
            if ((float)$this->discount_value > 100) {
                $this->addError('discount_value', 'Процент скидки не может быть больше 100.');
            }

            return;
        }

        if ($this->discount_type === self::DISCOUNT_FIXED && (float)$this->discount_value <= 0) {
            $this->addError('discount_value', 'Сумма скидки должна быть больше 0.');
        }
    }

    public function attributeLabels(): array
    {
        return [
            'title' => 'Название',
            'discount_type' => 'Тип скидки',
            'discount_value' => 'Размер скидки',
            'starts_at' => 'Начало',
            'ends_at' => 'Окончание',
            'scope_type' => 'Область',
            'catalog_model_id' => 'Модель',
            'catalog_product_id' => 'Товар (SKU)',
            'image_media_id' => 'Изображение',
        ];
    }

    public function getImageMedia()
    {
        return $this->hasOne(MediaFile::class, ['id' => 'image_media_id']);
    }

    public static function discountTypeLabels(): array
    {
        return [
            self::DISCOUNT_PERCENT => 'Процент',
            self::DISCOUNT_FIXED => 'Сумма',
        ];
    }

    public static function scopeTypeLabels(): array
    {
        return [
            self::SCOPE_MODEL => 'Вся модель',
            self::SCOPE_PRODUCT => 'Конкретный товар',
        ];
    }

    public function getCatalogModel()
    {
        return $this->hasOne(CatalogModel::class, ['id' => 'catalog_model_id']);
    }

    public function getCatalogProduct()
    {
        return $this->hasOne(CatalogProduct::class, ['id' => 'catalog_product_id']);
    }

    public function isActiveAt(string $at): bool
    {
        return strtotime($at) >= strtotime((string)$this->starts_at)
            && strtotime($at) <= strtotime((string)$this->ends_at);
    }
}
