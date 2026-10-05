<?php

namespace app\models;

use app\models\traits\AutoSlugTrait;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

class CatalogFabricCollection extends ActiveRecord
{
    use AutoSlugTrait;

    public const MATERIAL_KIND_FABRIC = 'Ткань';
    public const MATERIAL_KIND_LEATHER = 'Кожа';
    public const MATERIAL_KIND_ECO_LEATHER = 'Экокожа';
    public const MATERIAL_KIND_SPECIAL = 'Спец. покрытие';

    /** @var string[] */
    public const MATERIAL_KINDS = [
        self::MATERIAL_KIND_FABRIC,
        self::MATERIAL_KIND_LEATHER,
        self::MATERIAL_KIND_ECO_LEATHER,
        self::MATERIAL_KIND_SPECIAL,
    ];

    /** Число активных цветов (для админки, не колонка БД). */
    public int $activeColorsCount = 0;

    protected function slugSourceAttribute(): string
    {
        return 'name';
    }

    public static function tableName(): string
    {
        return '{{%catalog_fabric_collections}}';
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
            [['name'], 'required'],
            [['slug'], 'string', 'max' => 64],
            [['slug'], 'unique'],
            [['name'], 'string', 'max' => 255],
            [['material_kind'], 'string', 'max' => 32],
            [['material_kind'], 'in', 'range' => self::MATERIAL_KINDS],
            [['texture'], 'string', 'max' => 64],
            [['description', 'composition', 'care_instructions'], 'string'],
            [['density_gsm', 'roll_width_cm', 'martindale'], 'integer', 'min' => 0],
            [['meter_price_display'], 'string', 'max' => 64],
            [['price_category_id', 'price_category_line1_id'], 'integer'],
            [['price_category_id', 'price_category_line1_id'], 'exist', 'skipOnError' => true, 'targetClass' => CatalogPriceCategory::class, 'targetAttribute' => ['price_category_id' => 'id']],
            [['import_source'], 'string', 'max' => 64],
            [['import_row_hash'], 'string', 'max' => 64],
            [['sort_order'], 'integer'],
            [['is_active'], 'boolean'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'slug' => 'Slug',
            'name' => 'Название',
            'material_kind' => 'Категория ткани',
            'texture' => 'Фактура',
            'composition' => 'Состав',
            'density_gsm' => 'Плотность, г/м²',
            'roll_width_cm' => 'Ширина рулона, см',
            'martindale' => 'Износостойкость (циклы Мартиндейла)',
            'description' => 'Описание',
            'care_instructions' => 'Свойства',
            'meter_price_display' => 'Стоимость пог. м',
            'price_category_id' => 'Категория для коллекции А+',
            'price_category_line1_id' => 'Категория для коллекции Линия 1',
            'sort_order' => 'Порядок',
            'is_active' => 'Активна',
        ];
    }

    public function beforeValidate(): bool
    {
        if ($this->material_kind === null || trim((string)$this->material_kind) === '') {
            $this->material_kind = self::MATERIAL_KIND_FABRIC;
        }

        if ($this->price_category_id === '' || (int)$this->price_category_id === 0) {
            $this->price_category_id = null;
        }
        if ($this->price_category_line1_id === '' || (int)$this->price_category_line1_id === 0) {
            $this->price_category_line1_id = null;
        }

        foreach (['density_gsm', 'roll_width_cm', 'martindale'] as $attribute) {
            if ($this->{$attribute} === '' || $this->{$attribute} === null) {
                $this->{$attribute} = null;
                continue;
            }
            $this->{$attribute} = (int)$this->{$attribute};
        }

        return parent::beforeValidate();
    }

    /**
     * @return array<string, string>
     */
    public static function materialKindOptions(): array
    {
        $options = [];
        foreach (self::MATERIAL_KINDS as $kind) {
            $options[$kind] = $kind;
        }

        return $options;
    }

    public function getPriceCategoryForProducts(): ?int
    {
        if ($this->price_category_id !== null && (int)$this->price_category_id > 0) {
            return (int)$this->price_category_id;
        }

        return CatalogPriceCategory::getDefaultId();
    }

    public function getPriceCategoryNumberForApi(): ?int
    {
        if ($this->priceCategory === null) {
            return null;
        }

        return (int)$this->priceCategory->number;
    }

    public function getPriceCategory()
    {
        return $this->hasOne(CatalogPriceCategory::class, ['id' => 'price_category_id']);
    }

    public function getPriceCategoryLine1()
    {
        return $this->hasOne(CatalogPriceCategory::class, ['id' => 'price_category_line1_id']);
    }

    public function getColors()
    {
        return $this->hasMany(CatalogFabricColor::class, ['fabric_collection_id' => 'id'])
            ->orderBy(CatalogFabricColor::defaultSortOrder());
    }

    public function getActiveColors()
    {
        return $this->hasMany(CatalogFabricColor::class, ['fabric_collection_id' => 'id'])
            ->andWhere(['is_active' => true])
            ->orderBy(CatalogFabricColor::defaultSortOrder());
    }
}
