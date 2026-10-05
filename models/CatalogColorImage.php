<?php

namespace app\models;

use yii\db\ActiveRecord;

class CatalogColorImage extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%catalog_color_images}}';
    }

    public function rules(): array
    {
        return [
            [['color_id', 'media_file_id'], 'required'],
            [['color_id', 'media_file_id', 'sort_order'], 'integer'],
            [['created_at'], 'safe'],
            [['media_file_id'], 'unique', 'targetAttribute' => ['color_id', 'media_file_id']],
        ];
    }

    public function getColor()
    {
        return $this->hasOne(CatalogColor::class, ['id' => 'color_id']);
    }

    public function getMedia()
    {
        return $this->hasOne(MediaFile::class, ['id' => 'media_file_id']);
    }
}
