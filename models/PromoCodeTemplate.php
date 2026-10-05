<?php

namespace app\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

class PromoCodeTemplate extends ActiveRecord
{
    public const TYPE_EXHIBITION = 'exhibition';
    public const TYPE_NOVELTY = 'novelty';
    public const TYPE_CUSTOM = 'custom';

    public const CODE_EXHIBITION = 'VYSTAVKA';

    public static function tableName(): string
    {
        return '{{%promo_code_templates}}';
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
            [['code', 'title'], 'required'],
            [['code'], 'string', 'max' => 64],
            [['code'], 'unique'],
            [['title'], 'string', 'max' => 255],
            [['discount_percent'], 'number', 'min' => 0, 'max' => 100],
            [['type'], 'in', 'range' => [self::TYPE_EXHIBITION, self::TYPE_NOVELTY, self::TYPE_CUSTOM]],
            [['is_single_use', 'is_active'], 'boolean'],
            [['default_valid_days'], 'integer', 'min' => 1],
            [['valid_until'], 'date', 'format' => 'php:Y-m-d'],
        ];
    }

    public static function findExhibitionTemplate(): ?self
    {
        return static::findOne(['code' => self::CODE_EXHIBITION, 'is_active' => true]);
    }

    public static function typeLabels(): array
    {
        return [
            self::TYPE_EXHIBITION => 'Экспозиция',
            self::TYPE_NOVELTY => 'Новинка',
            self::TYPE_CUSTOM => 'Произвольный',
        ];
    }

    public function getTypeLabel(): string
    {
        return self::typeLabels()[$this->type] ?? $this->type;
    }

    public function attributeLabels(): array
    {
        return [
            'code' => 'Код',
            'title' => 'Название',
            'discount_percent' => 'Скидка, %',
            'type' => 'Тип',
            'is_single_use' => 'Одноразовый',
            'is_active' => 'Активен',
            'default_valid_days' => 'Срок действия, дней',
            'valid_until' => 'Действует до',
        ];
    }

    public function isSystemTemplate(): bool
    {
        return $this->type !== self::TYPE_CUSTOM;
    }
}
