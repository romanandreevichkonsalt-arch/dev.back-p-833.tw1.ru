<?php

namespace app\models;

use app\models\traits\AutoSlugTrait;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

class CatalogCollection extends ActiveRecord
{
    use AutoSlugTrait;

    protected function slugSourceAttribute(): string
    {
        return 'name';
    }
    public static function tableName(): string
    {
        return '{{%catalog_collections}}';
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

    public function beforeValidate(): bool
    {
        if (!parent::beforeValidate()) {
            return false;
        }

        if ($this->image_id === '' || $this->image_id === '0') {
            $this->image_id = null;
        }

        if ($this->direction_id === '') {
            $this->direction_id = null;
        }

        $name = trim((string)$this->name);
        $this->applyAutoSlug();

        if ($this->label === '') {
            $this->label = 'Коллекция';
        }

        if ($this->title === '' && $name !== '') {
            $this->title = $name;
        }

        if (trim((string)$this->href) === '' && $this->slug !== '') {
            $this->href = '/catalog/' . $this->slug;
        }

        return true;
    }

    public function rules(): array
    {
        return [
            [['direction_id', 'name'], 'required'],
            [['description'], 'string'],
            [['slug'], 'string', 'max' => 64],
            [['slug'], 'unique'],
            [['label', 'name', 'title', 'cta_label'], 'string', 'max' => 255],
            [['href'], 'string', 'max' => 512],
            [['image_position'], 'string', 'max' => 64],
            [['direction_id', 'image_id', 'sort_order'], 'integer'],
            [['title_uppercase', 'is_active'], 'boolean'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'direction_id' => 'Направление',
            'slug' => 'Slug',
            'name' => 'Название коллекции',
            'label' => 'Подпись',
            'title' => 'Заголовок',
            'title_uppercase' => 'Заголовок капсом',
            'description' => 'Описание',
            'href' => 'Ссылка',
            'cta_label' => 'Текст кнопки',
            'image_id' => 'Изображение',
            'sort_order' => 'Порядок',
            'is_active' => 'Активна',
        ];
    }

    public function getDirection()
    {
        return $this->hasOne(CatalogDirection::class, ['id' => 'direction_id']);
    }

    /** @deprecated use getDirection() */
    public function getGroup()
    {
        return $this->getDirection();
    }

    public function getCollectionImages()
    {
        return $this->hasMany(CatalogCollectionImage::class, ['collection_id' => 'id'])
            ->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC]);
    }

    public function getImage()
    {
        return $this->hasOne(MediaFile::class, ['id' => 'image_id']);
    }

    public function syncPrimaryImage(): void
    {
        $first = CatalogCollectionImage::find()
            ->where(['collection_id' => $this->id])
            ->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC])
            ->one();

        $this->image_id = $first?->media_file_id;
        $this->save(false, ['image_id', 'updated_at']);
    }

    /**
     * @return array<int, array{src: string, alt: string}>
     */
    public function getImagesApiPayload(): array
    {
        $images = [];
        foreach ($this->collectionImages as $link) {
            if ($link->media === null) {
                continue;
            }
            $images[] = $link->media->toApiImagePayload($link->media->alt ?? $link->media->filename);
        }

        return $images;
    }

    public function getProducts()
    {
        return $this->hasMany(CatalogProduct::class, ['collection_id' => 'id']);
    }

    public function getDisplayName(): string
    {
        return $this->name ?: $this->title;
    }

    public function afterSave($insert, $changedAttributes): void
    {
        parent::afterSave($insert, $changedAttributes);

        if ($insert) {
            return;
        }

        if (!array_key_exists('name', $changedAttributes) && !array_key_exists('title', $changedAttributes)) {
            return;
        }

        \Yii::$container->get(\app\services\catalog\CatalogModelProductSyncService::class)
            ->refreshDerivedNamesForCatalogCollectionId((int)$this->id);
    }
}
