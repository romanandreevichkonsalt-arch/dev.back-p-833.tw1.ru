<?php

namespace app\models;

use app\models\traits\AutoSlugTrait;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

class CatalogSurfaceMaterial extends ActiveRecord
{
    use AutoSlugTrait;

    public const TYPE_WOOD = 'Дерево';
    public const TYPE_METAL = 'Металл';
    public const TYPE_LACQUER = 'Лак';

    /** @var string[] */
    public const MATERIAL_TYPES = [
        self::TYPE_WOOD,
        self::TYPE_METAL,
        self::TYPE_LACQUER,
    ];

    protected function slugSourceAttribute(): string
    {
        return 'name';
    }

    public static function tableName(): string
    {
        return '{{%catalog_surface_materials}}';
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
            [['material_type', 'name'], 'required'],
            [['material_type'], 'string', 'max' => 32],
            [['name'], 'string', 'max' => 255],
            [['slug'], 'string', 'max' => 64],
            [['slug'], 'unique', 'targetAttribute' => ['material_type', 'slug']],
            [['applied_models_text', 'description'], 'string'],
            [['registry_number', 'photo_media_id', 'texture_media_id', 'sort_order'], 'integer'],
            [['source_photo_url', 'source_texture_url'], 'string', 'max' => 512],
            [['import_source'], 'string', 'max' => 64],
            [['import_row_hash'], 'string', 'max' => 64],
            [['is_active'], 'boolean'],
            [['photo_media_id'], 'exist', 'skipOnEmpty' => true, 'targetClass' => MediaFile::class, 'targetAttribute' => ['photo_media_id' => 'id']],
            [['texture_media_id'], 'exist', 'skipOnEmpty' => true, 'targetClass' => MediaFile::class, 'targetAttribute' => ['texture_media_id' => 'id']],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'registry_number' => '№',
            'material_type' => 'Тип',
            'name' => 'Название / тонировка',
            'slug' => 'Slug',
            'applied_models_text' => 'Применяется на модели',
            'description' => 'Описание',
            'source_photo_url' => 'Ссылка на фото',
            'photo_media_id' => 'Фото',
            'source_texture_url' => 'Ссылка на текстуру',
            'texture_media_id' => 'Текстура (изображение)',
            'sort_order' => 'Порядок',
            'is_active' => 'Активен',
        ];
    }

    public function beforeValidate(): bool
    {
        foreach (['photo_media_id', 'texture_media_id', 'registry_number'] as $attribute) {
            if ($this->{$attribute} === '' || (int)$this->{$attribute} === 0) {
                $this->{$attribute} = null;
            }
        }

        $this->material_type = trim((string)$this->material_type);

        return parent::beforeValidate();
    }

    public function getPhotoMedia()
    {
        return $this->hasOne(MediaFile::class, ['id' => 'photo_media_id']);
    }

    public function getTextureMedia()
    {
        return $this->hasOne(MediaFile::class, ['id' => 'texture_media_id']);
    }

    public function getCatalogCollections()
    {
        return $this->hasMany(CatalogCollection::class, ['id' => 'collection_id'])
            ->viaTable('{{%catalog_surface_material_collections}}', ['surface_material_id' => 'id']);
    }

    /**
     * @return int[]
     */
    public function getLinkedCollectionIds(): array
    {
        return array_map('intval', \Yii::$app->db->createCommand(
            'SELECT collection_id FROM {{%catalog_surface_material_collections}} WHERE surface_material_id = :id',
            ['id' => $this->id]
        )->queryColumn());
    }

    /**
     * @param int[] $collectionIds
     */
    public function syncCollectionLinks(array $collectionIds): void
    {
        $collectionIds = array_values(array_unique(array_filter(array_map('intval', $collectionIds))));

        $existing = $this->getLinkedCollectionIds();
        $now = date('Y-m-d H:i:s');

        foreach ($collectionIds as $collectionId) {
            if (in_array($collectionId, $existing, true)) {
                continue;
            }
            \Yii::$app->db->createCommand()->insert('{{%catalog_surface_material_collections}}', [
                'surface_material_id' => $this->id,
                'collection_id' => $collectionId,
                'created_at' => $now,
            ])->execute();
        }
    }

    /**
     * @param int[] $collectionIds
     */
    public function replaceCollectionLinks(array $collectionIds): void
    {
        $collectionIds = array_values(array_unique(array_filter(array_map('intval', $collectionIds))));
        $existing = $this->getLinkedCollectionIds();

        foreach (array_diff($existing, $collectionIds) as $collectionId) {
            \Yii::$app->db->createCommand()->delete(
                '{{%catalog_surface_material_collections}}',
                ['surface_material_id' => $this->id, 'collection_id' => $collectionId]
            )->execute();
        }

        $this->syncCollectionLinks($collectionIds);
    }

    public static function materialTypeOptions(): array
    {
        $options = [];
        foreach (self::MATERIAL_TYPES as $type) {
            $options[$type] = $type;
        }

        return $options;
    }
}
