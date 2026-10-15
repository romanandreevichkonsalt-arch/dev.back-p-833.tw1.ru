<?php

namespace app\models;

use yii\db\ActiveRecord;

class CatalogFabricColorLibraryPhoto extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%catalog_fabric_color_library_photos}}';
    }

    public function rules(): array
    {
        return [
            [['fabric_color_id', 'media_file_id'], 'required'],
            [['fabric_color_id', 'media_file_id', 'sort_order'], 'integer'],
            [['created_at'], 'safe'],
            [['media_file_id'], 'unique', 'targetAttribute' => ['fabric_color_id', 'media_file_id']],
        ];
    }

    public function getFabricColor()
    {
        return $this->hasOne(CatalogFabricColor::class, ['id' => 'fabric_color_id']);
    }

    public function getMedia()
    {
        return $this->hasOne(MediaFile::class, ['id' => 'media_file_id']);
    }
}
