<?php

namespace app\models;

use app\models\traits\AutoSlugTrait;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

class CatalogColor extends ActiveRecord
{
    use AutoSlugTrait;

    protected function slugSourceAttribute(): string
    {
        return 'label';
    }

    public static function tableName(): string
    {
        return '{{%catalog_colors}}';
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
            [['label'], 'required'],
            [['slug'], 'string', 'max' => 64],
            [['slug'], 'unique'],
            [['label'], 'string', 'max' => 255],
            [['hex_color'], 'string', 'max' => 7],
            [['hex_color'], 'match', 'pattern' => '/^#[0-9A-Fa-f]{6}$/', 'skipOnEmpty' => true],
            [['sort_order', 'swatch_media_id'], 'integer'],
            [['is_active'], 'boolean'],
            [['swatch_media_id'], 'exist', 'skipOnError' => true, 'targetClass' => MediaFile::class, 'targetAttribute' => ['swatch_media_id' => 'id']],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'slug' => 'Slug',
            'label' => 'Название',
            'hex_color' => 'Цвет (hex)',
            'swatch_media_id' => 'Фото образца',
            'sort_order' => 'Порядок',
            'is_active' => 'Активен',
        ];
    }

    public function beforeValidate(): bool
    {
        if ($this->swatch_media_id === '' || (int)$this->swatch_media_id === 0) {
            $this->swatch_media_id = null;
        }
        if ($this->hex_color === '') {
            $this->hex_color = null;
        }

        $this->applyAutoSlug();

        return parent::beforeValidate();
    }

    /**
     * @return static[]
     */
    public static function findActiveOrdered(): array
    {
        return static::find()
            ->where(['is_active' => true])
            ->orderBy(['sort_order' => SORT_ASC, 'label' => SORT_ASC, 'id' => SORT_ASC])
            ->all();
    }

    public function getSwatchMedia()
    {
        return $this->hasOne(MediaFile::class, ['id' => 'swatch_media_id']);
    }

    public function getColorImages()
    {
        return $this->hasMany(CatalogColorImage::class, ['color_id' => 'id'])
            ->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC]);
    }

    public function getFabricCollectionLinks()
    {
        return $this->hasMany(CatalogFabricColor::class, ['color_id' => 'id']);
    }

    public function getSwatchCircleStyle(): string
    {
        if ($this->swatchMedia !== null) {
            $url = str_replace('"', '%22', $this->swatchMedia->getPublicUrl());

            return 'background-image: url("' . $url . '");';
        }
        if ($this->hex_color !== null && $this->hex_color !== '') {
            return 'background-color: ' . $this->hex_color . ';';
        }

        return 'background-color: #d4d0c8;';
    }
}
