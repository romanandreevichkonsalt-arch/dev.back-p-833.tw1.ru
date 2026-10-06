<?php

namespace app\models;

use app\helpers\CatalogFabricBaseColorPalette;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;
use yii\db\Expression;

class CatalogFabricColor extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%catalog_fabric_collection_colors}}';
    }

    /**
     * Порядок цветов коллекции: рекомендуемые по position_number, затем по label (api_label / design_code).
     *
     * @return array<int|string, int|Expression>
     */
    public static function defaultSortOrder(): array
    {
        return [
            new Expression('CASE WHEN [[is_recommended_fabric]] = 1 THEN 0 ELSE 1 END'),
            new Expression(
                'CASE WHEN [[is_recommended_fabric]] = 1'
                . ' THEN COALESCE([[position_number]], 2147483647) ELSE 0 END'
            ),
            new Expression('COALESCE(NULLIF([[api_label]], \'\'), [[design_code]])'),
            'id' => SORT_ASC,
        ];
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
            [['fabric_collection_id', 'design_code'], 'required'],
            [['fabric_collection_id', 'color_id', 'sort_order', 'position_number'], 'integer'],
            [['swatch_media_id', 'pbr_media_id'], 'integer'],
            [['design_code'], 'string', 'max' => 255],
            [['api_label'], 'string', 'max' => 255],
            [['source_photo_url'], 'string', 'max' => 512],
            [['import_comment', 'description'], 'string'],
            [['is_active', 'is_recommended_fabric'], 'boolean'],
            [['fabric_collection_id', 'design_code'], 'unique', 'targetAttribute' => ['fabric_collection_id', 'design_code']],
            [['fabric_collection_id'], 'exist', 'targetClass' => CatalogFabricCollection::class, 'targetAttribute' => ['fabric_collection_id' => 'id']],
            [['color_id'], 'exist', 'skipOnEmpty' => true, 'targetClass' => CatalogColor::class, 'targetAttribute' => ['color_id' => 'id']],
            [['swatch_media_id'], 'exist', 'skipOnEmpty' => true, 'targetClass' => MediaFile::class, 'targetAttribute' => ['swatch_media_id' => 'id']],
            [['pbr_media_id'], 'exist', 'skipOnEmpty' => true, 'targetClass' => MediaFile::class, 'targetAttribute' => ['pbr_media_id' => 'id']],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'fabric_collection_id' => 'Коллекция ткани',
            'color_id' => 'Цвет',
            'design_code' => 'Название',
            'swatch_media_id' => 'Фото образца',
            'is_active' => 'Активен',
            'is_recommended_fabric' => 'Реком. ткань',
            'position_number' => '№ позиции',
            'description' => 'Описание цветодизайна',
        ];
    }

    public function beforeValidate(): bool
    {
        foreach (['color_id', 'swatch_media_id', 'pbr_media_id', 'position_number'] as $attribute) {
            if ($this->{$attribute} === '' || (int)$this->{$attribute} === 0) {
                $this->{$attribute} = null;
            }
        }

        return parent::beforeValidate();
    }

    public function getDisplayLabel(): string
    {
        $parts = array_filter([
            $this->design_code !== '' ? $this->design_code : null,
            $this->catalogColor?->label,
        ], static fn (?string $part): bool => $part !== null && $part !== '');

        return $parts !== [] ? implode(', ', $parts) : ('Код ' . $this->design_code);
    }

    public function getLabel(): string
    {
        return $this->getDisplayLabel();
    }

    /**
     * @param string|null $catalogColorLabel оставлен для совместимости со старой миграцией; в label не используется
     */
    public static function buildApiLabel(string $collectionName, string $designCode, ?string $catalogColorLabel = null): string
    {
        $collectionName = trim($collectionName);
        $designCode = trim($designCode);

        if ($collectionName !== '' && $designCode !== '') {
            return $collectionName . ' ' . $designCode;
        }

        if ($collectionName !== '') {
            return $collectionName;
        }

        return $designCode;
    }

    public function resolveApiLabel(): string
    {
        $collectionName = (string)($this->fabricCollection?->name ?? '');
        if ($collectionName === '' && $this->fabric_collection_id !== null) {
            $collectionName = (string)CatalogFabricCollection::find()
                ->select('name')
                ->where(['id' => (int)$this->fabric_collection_id])
                ->scalar();
        }

        return self::buildApiLabel($collectionName, (string)$this->design_code);
    }

    public function getApiLabel(): string
    {
        $stored = trim((string)($this->api_label ?? ''));
        if ($stored !== '') {
            return $stored;
        }

        return $this->resolveApiLabel();
    }

    public function beforeSave($insert): bool
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }

        $this->api_label = $this->resolveApiLabel();

        return true;
    }

    /**
     * Название цвета для SKU и карточки товара — без кода цветодизайна.
     */
    public function getProductColorLabel(): string
    {
        $catalogLabel = trim((string)($this->catalogColor?->label ?? ''));
        if ($catalogLabel !== '') {
            return $catalogLabel;
        }

        $designCode = trim((string)$this->design_code);
        if ($designCode !== '') {
            return $designCode;
        }

        return '';
    }

    /**
     * Русское название из справочника catalog_colors (без design-code).
     */
    public function getCatalogColorName(): ?string
    {
        $label = trim((string)($this->catalogColor?->label ?? ''));

        return $label !== '' ? $label : null;
    }

    public function getSlug(): string
    {
        return (string)($this->catalogColor->slug ?? $this->design_code);
    }

    public function getHexColor(): ?string
    {
        $value = $this->catalogColor->hex_color ?? null;

        return $value !== null && $value !== '' ? $value : null;
    }

    public function resolveListingHexColor(): string
    {
        $hex = $this->getHexColor();
        if ($hex !== null) {
            return $hex;
        }

        if ($this->catalogColor !== null) {
            $hex = CatalogFabricBaseColorPalette::resolveHex(
                $this->catalogColor->label,
                $this->catalogColor->slug
            );
            if ($hex !== null && $hex !== '') {
                return $hex;
            }
        }

        return '#d4d0c8';
    }

    public function resolveListingSwatchSrc(): ?string
    {
        if ($this->swatchMedia !== null && $this->swatchMedia->isImage()) {
            return $this->swatchMedia->getPublicUrl('mini');
        }

        if ($this->catalogColor?->swatchMedia !== null && $this->catalogColor->swatchMedia->isImage()) {
            return $this->catalogColor->swatchMedia->getPublicUrl('mini');
        }

        return null;
    }

    /**
     * Превью оттенка для листинга каталога (hexColor + опциональное фото).
     *
     * @return array{hexColor: string, alt: string, src?: string}
     */
    public function toListingSwatchPayload(): array
    {
        $alt = $this->getCatalogColorName() ?? trim($this->design_code);
        if ($alt === '') {
            $alt = $this->getApiLabel();
        }

        $payload = [
            'hexColor' => $this->resolveListingHexColor(),
            'alt' => $alt,
        ];

        $src = $this->resolveListingSwatchSrc();
        if ($src !== null && $src !== '') {
            $payload['src'] = $src;
        }

        return $payload;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function collectSwatchesApiPayload(): array
    {
        $swatches = [];
        if ($this->swatchMedia !== null) {
            $swatches[] = $this->swatchMedia->toApiImagePayload(
                $this->swatchMedia->alt ?? $this->getProductColorLabel()
            );
        }
        foreach ($this->colorImages as $link) {
            if ($link->media === null) {
                continue;
            }
            $swatches[] = $link->media->toApiImagePayload($link->media->alt ?? $link->media->filename);
        }

        return $swatches;
    }

    /**
     * @return array<string, mixed>
     */
    public function toApiPayload(): array
    {
        return [
            'id' => $this->getSlug(),
            'label' => $this->getApiLabel(),
            'colorName' => $this->getCatalogColorName(),
            'colorId' => $this->catalogColor?->slug,
            'category' => $this->fabricCollection?->getPriceCategoryNumberForApi(),
            'meterPrice' => $this->fabricCollection?->meter_price_display,
            'hexColor' => $this->getHexColor(),
            'collection' => $this->fabricCollection?->name,
            'texture' => $this->fabricCollection?->texture,
            'isRecommendedFabric' => (bool)$this->is_recommended_fabric,
            'positionNumber' => $this->position_number !== null ? (int)$this->position_number : null,
            'description' => $this->getDescriptionForApi(),
            'swatches' => $this->collectSwatchesApiPayload(),
        ];
    }

    public function getDescriptionForApi(): ?string
    {
        $description = trim((string)($this->description ?? ''));

        return $description !== '' ? $description : null;
    }

    public function getSwatchPreviewUrl(): ?string
    {
        if ($this->swatchMedia !== null) {
            return $this->swatchMedia->getPublicUrl();
        }

        return $this->catalogColor?->swatchMedia?->getPublicUrl();
    }

    public function getSwatchCircleStyle(): string
    {
        if ($this->swatchMedia !== null) {
            $url = str_replace('"', '%22', $this->swatchMedia->getPublicUrl());

            return 'background-image:url("' . $url . '");background-size:cover;background-position:center;';
        }

        return $this->catalogColor?->getSwatchCircleStyle() ?? 'background-color: #d4d0c8;';
    }

    public function getCatalogColorCircleStyle(): string
    {
        $hex = $this->catalogColor?->hex_color;
        if (($hex === null || $hex === '') && $this->catalogColor !== null) {
            $hex = CatalogFabricBaseColorPalette::resolveHex(
                $this->catalogColor->label,
                $this->catalogColor->slug
            );
        }
        if ($hex !== null && $hex !== '') {
            return 'background-color: ' . $hex . ';';
        }

        return 'background-color: #d4d0c8;';
    }

    public function getFabricCollection()
    {
        return $this->hasOne(CatalogFabricCollection::class, ['id' => 'fabric_collection_id']);
    }

    public function getCatalogColor()
    {
        return $this->hasOne(CatalogColor::class, ['id' => 'color_id']);
    }

    public function getSwatchMedia()
    {
        return $this->hasOne(MediaFile::class, ['id' => 'swatch_media_id']);
    }

    public function getPbrMedia()
    {
        return $this->hasOne(MediaFile::class, ['id' => 'pbr_media_id']);
    }

    public function getColorImages()
    {
        return $this->hasMany(CatalogColorImage::class, ['color_id' => 'color_id'])
            ->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC]);
    }

    public function beforeDelete(): bool
    {
        if (!parent::beforeDelete()) {
            return false;
        }

        \Yii::$container->get(\app\services\catalog\CatalogModelProductSyncService::class)
            ->deleteProductsForFabricColorIds([(int)$this->id]);

        return true;
    }

    public function afterDelete(): void
    {
        parent::afterDelete();
        \Yii::$container->get(\app\services\catalog\CatalogModelProductSyncService::class)
            ->syncForFabricCollectionId((int)$this->fabric_collection_id);
    }
}
