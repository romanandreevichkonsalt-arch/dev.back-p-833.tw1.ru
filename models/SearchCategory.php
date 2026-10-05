<?php

namespace app\models;

use app\models\traits\AutoSlugTrait;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

class SearchCategory extends ActiveRecord
{
    use AutoSlugTrait;
    public static function tableName(): string
    {
        return '{{%search_categories}}';
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
            [['slug', 'label', 'icon', 'href'], 'required'],
            [['slug'], 'string', 'max' => 64],
            [['slug'], 'unique'],
            [['label'], 'string', 'max' => 255],
            [['icon'], 'string', 'max' => 64],
            [['href'], 'string', 'max' => 512],
            [['sort_order'], 'integer'],
            [['is_active'], 'boolean'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'slug' => 'Slug',
            'label' => 'Название',
            'icon' => 'Иконка',
            'href' => 'Ссылка',
            'sort_order' => 'Порядок',
            'is_active' => 'Активна',
        ];
    }

    public function toApiItem(): array
    {
        return [
            'id' => $this->slug,
            'label' => $this->label,
            'icon' => $this->icon,
            'href' => $this->href,
        ];
    }
}
