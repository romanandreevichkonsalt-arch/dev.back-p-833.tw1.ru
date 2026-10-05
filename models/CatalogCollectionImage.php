<?php

namespace app\models;

use yii\db\ActiveRecord;

class CatalogCollectionImage extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%catalog_collection_images}}';
    }

    public function rules(): array
    {
        return [
            [['collection_id', 'media_file_id'], 'required'],
            [['collection_id', 'media_file_id', 'sort_order'], 'integer'],
            [['created_at'], 'safe'],
            [['media_file_id'], 'unique', 'targetAttribute' => ['collection_id', 'media_file_id']],
        ];
    }

    public function getCollection()
    {
        return $this->hasOne(CatalogCollection::class, ['id' => 'collection_id']);
    }

    public function getMedia()
    {
        return $this->hasOne(MediaFile::class, ['id' => 'media_file_id']);
    }
}
