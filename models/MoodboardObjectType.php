<?php

namespace app\models;

use yii\db\ActiveRecord;

class MoodboardObjectType extends ActiveRecord
{
    public const CODE_MODEL = 'model';
    public const CODE_FABRIC = 'fabric';
    public const CODE_SURFACE_MATERIAL = 'surface_material';
    public const CODE_PRODUCT = 'product';

    /** @var string[] */
    public const ACTIVE_CODES = [
        self::CODE_MODEL,
        self::CODE_FABRIC,
        self::CODE_SURFACE_MATERIAL,
        self::CODE_PRODUCT,
    ];

    public static function tableName(): string
    {
        return '{{%moodboard_object_types}}';
    }

    public static function primaryKey(): array
    {
        return ['code'];
    }

    public function rules(): array
    {
        return [
            [['code', 'label', 'ref_table'], 'required'],
            [['code'], 'string', 'max' => 32],
            [['label'], 'string', 'max' => 128],
            [['ref_table'], 'string', 'max' => 64],
            [['schema_version'], 'integer'],
            [['is_active'], 'boolean'],
            [['created_at'], 'safe'],
        ];
    }
}
