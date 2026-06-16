<?php

namespace app\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;
use yii\db\Expression;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $type
 * @property bool $is_filterable
 * @property bool $is_required
 * @property string $created_at
 * @property string $updated_at
 */
class AttributeDefinition extends ActiveRecord
{
    public const TYPE_STRING = 'string';
    public const TYPE_NUMBER = 'number';
    public const TYPE_BOOL = 'bool';

    public static function tableName(): string
    {
        return '{{%attribute_definitions}}';
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
            [['code', 'name', 'type'], 'required'],
            [['is_filterable', 'is_required'], 'boolean'],
            [['code'], 'string', 'max' => 64],
            [['name'], 'string', 'max' => 255],
            [['type'], 'in', 'range' => [self::TYPE_STRING, self::TYPE_NUMBER, self::TYPE_BOOL]],
            [['code'], 'unique'],
            [['code'], 'match', 'pattern' => '/^[a-z][a-z0-9_]*$/', 'message' => 'Код атрибута должен быть в snake_case.'],
        ];
    }

    public static function mapByCode(): array
    {
        $map = [];
        foreach (self::find()->all() as $definition) {
            $map[$definition->code] = $definition;
        }

        return $map;
    }
}
