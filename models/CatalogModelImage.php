<?php

namespace app\models;

use yii\db\ActiveRecord;

class CatalogModelImage extends ActiveRecord
{
    public const PURPOSE_ANGLE = 'angle';
    public const PURPOSE_INTERIOR = 'interior';

    public static function tableName(): string
    {
        return '{{%catalog_model_images}}';
    }

    public function rules(): array
    {
        return [
            [['model_id', 'media_file_id'], 'required'],
            [['model_id', 'media_file_id', 'sort_order'], 'integer'],
            [['purpose'], 'string', 'max' => 16],
            [['purpose'], 'in', 'range' => [self::PURPOSE_ANGLE, self::PURPOSE_INTERIOR]],
            [['created_at'], 'safe'],
            [['media_file_id'], 'unique', 'targetAttribute' => ['model_id', 'media_file_id']],
        ];
    }

    public function getModel()
    {
        return $this->hasOne(CatalogModel::class, ['id' => 'model_id']);
    }

    public function getMedia()
    {
        return $this->hasOne(MediaFile::class, ['id' => 'media_file_id']);
    }
}
