<?php

namespace app\models;

use yii\db\ActiveRecord;

class CatalogModelDimensionImage extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%catalog_model_dimension_images}}';
    }

    public function rules(): array
    {
        return [
            [['model_id', 'media_file_id'], 'required'],
            [['model_id', 'media_file_id', 'sort_order'], 'integer'],
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
