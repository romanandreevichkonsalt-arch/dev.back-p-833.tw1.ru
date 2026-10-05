<?php

namespace app\models;

use yii\db\ActiveRecord;

class MediaFolder extends ActiveRecord
{
    public const SLUG_ANGLES = 'angles';
    public const SLUG_INTERIOR = 'interior';
    public const SLUG_TECH = 'tech';
    public const SLUG_VIDEO = 'video';
    public const SLUG_FABRICS = 'fabrics';
    public const SLUG_SURFACE_MATERIALS = 'surface-materials';
    public const LABEL_SURFACE_MATERIALS = 'Материалы';
    public const SLUG_BANNERS = 'banners';
    public const SLUG_DOCUMENTS = 'documents';
    public const SLUG_MODELS_3D = 'models-3d';
    public const SLUG_GENERAL = 'general';
    public const SLUG_MOODBOARDS = 'moodboards';

    public static function tableName(): string
    {
        return '{{%media_folders}}';
    }

    public function rules(): array
    {
        return [
            [['slug', 'label'], 'required'],
            [['slug'], 'string', 'max' => 32],
            [['slug'], 'unique'],
            [['label'], 'string', 'max' => 64],
            [['sort_order'], 'integer'],
        ];
    }

    public function getFiles()
    {
        return $this->hasMany(MediaFile::class, ['folder_id' => 'id']);
    }

    public static function findBySlug(string $slug): ?self
    {
        return static::find()->where(['slug' => $slug])->one();
    }

    public static function idBySlug(string $slug): ?int
    {
        $folder = static::findBySlug($slug);

        return $folder !== null ? (int)$folder->id : null;
    }
}
