<?php

namespace app\models;

use app\models\traits\AutoSlugTrait;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

class CatalogDirection extends ActiveRecord
{
    use AutoSlugTrait;

    public static function tableName(): string
    {
        return '{{%catalog_directions}}';
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
            [['slug', 'label'], 'required'],
            [['slug'], 'string', 'max' => 64],
            [['slug'], 'unique'],
            [['label'], 'string', 'max' => 255],
            [['sort_order'], 'integer'],
            [['is_active'], 'boolean'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'slug' => 'Slug',
            'label' => 'Название',
            'sort_order' => 'Порядок',
            'is_active' => 'Активно',
        ];
    }

    public function getCollections()
    {
        return $this->hasMany(CatalogCollection::class, ['direction_id' => 'id'])
            ->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC]);
    }

    /**
     * @return CatalogSubcategory[]
     */
    public function getMenuSubcategories(): array
    {
        return CatalogSubcategory::find()
            ->where(['is_active' => true])
            ->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC])
            ->all();
    }
}
