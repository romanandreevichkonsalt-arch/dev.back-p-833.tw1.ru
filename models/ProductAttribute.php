<?php

namespace app\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\db\Expression;

/**
 * @property int $id
 * @property int $product_id
 * @property string $attribute_code
 * @property string|null $value_string
 * @property string|null $value_number
 * @property bool|null $value_bool
 * @property string $created_at
 * @property string $updated_at
 *
 * @property AttributeDefinition|null $definition
 */
class ProductAttribute extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%product_attributes}}';
    }

    public function behaviors(): array
    {
        return [
            [
                'class' => TimestampBehavior::class,
                'value' => new Expression('NOW()'),
            ],
        ];
    }

    public function rules(): array
    {
        return [
            [['product_id', 'attribute_code'], 'required'],
            [['product_id'], 'integer'],
            [['value_string'], 'string', 'max' => 255],
            [['value_number'], 'number'],
            [['value_bool'], 'boolean'],
            [['attribute_code'], 'string', 'max' => 64],
            [
                ['attribute_code'],
                'exist',
                'targetClass' => AttributeDefinition::class,
                'targetAttribute' => ['attribute_code' => 'code'],
            ],
            [['product_id', 'attribute_code'], 'unique', 'targetAttribute' => ['product_id', 'attribute_code']],
        ];
    }

    public function getDefinition(): ActiveQuery
    {
        return $this->hasOne(AttributeDefinition::class, ['code' => 'attribute_code']);
    }

    public function toApiArray(): array
    {
        $definition = $this->definition;
        $type = $definition ? $definition->type : 'string';

        return [
            'code' => $this->attribute_code,
            'name' => $definition ? $definition->name : $this->attribute_code,
            'type' => $type,
            'value' => $this->getTypedValue($type),
        ];
    }

    /**
     * @return string|float|bool|null
     */
    public function getTypedValue(string $type)
    {
        if ($type === AttributeDefinition::TYPE_NUMBER) {
            return $this->value_number !== null ? (float) $this->value_number : null;
        }

        if ($type === AttributeDefinition::TYPE_BOOL) {
            return $this->value_bool !== null ? (bool) $this->value_bool : null;
        }

        return $this->value_string;
    }

    public static function valueColumnsForType(string $type, $value): array
    {
        if ($type === AttributeDefinition::TYPE_NUMBER) {
            return [
                'value_string' => null,
                'value_number' => $value,
                'value_bool' => null,
            ];
        }

        if ($type === AttributeDefinition::TYPE_BOOL) {
            return [
                'value_string' => null,
                'value_number' => null,
                'value_bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            ];
        }

        return [
            'value_string' => $value !== null ? (string) $value : null,
            'value_number' => null,
            'value_bool' => null,
        ];
    }
}
