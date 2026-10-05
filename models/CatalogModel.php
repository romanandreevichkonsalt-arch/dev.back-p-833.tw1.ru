<?php

namespace app\models;

use app\helpers\SlugHelper;
use app\services\catalog\CatalogCategorySlug;
use app\services\catalog\CatalogListingValueParser;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

class CatalogModel extends ActiveRecord
{

    public static function tableName(): string
    {
        return '{{%catalog_models}}';
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
            [['collection_id', 'category_id', 'subcategory_id', 'title'], 'required'],
            [['slug'], 'string', 'max' => 64],
            [['slug'], 'unique'],
            [['title', 'subtitle'], 'string', 'max' => 255],
            [['description'], 'string'],
            [['overall_size', 'seat_depth', 'seat_height', 'armrest_width', 'leg_height'], 'string', 'max' => 64],
            [['width_mm', 'height_mm', 'depth_mm', 'corner_depth_mm', 'sleeping_place_width_mm', 'sleeping_place_depth_mm'], 'integer'],
            [['frame', 'frame_spec', 'mechanism', 'foundation', 'filling', 'filling_spec', 'additional', 'upholstery', 'supports'], 'string'],
            [[
                'collection_id',
                'category_id',
                'subcategory_id',
                'layout_id',
                'badge_id',
                'video_id',
                'file_3d_id',
                'sort_order',
            ], 'integer'],
            [['fitting_room_url', 'file_3d_url', 'tech_photos_folder_url'], 'string', 'max' => 512],
            [['polygons_3d'], 'string', 'max' => 64],
            [['video_id'], 'exist', 'skipOnError' => true, 'targetClass' => MediaFile::class, 'targetAttribute' => ['video_id' => 'id'], 'filter' => ['kind' => MediaFile::KIND_VIDEO]],
            [['file_3d_id'], 'exist', 'skipOnError' => true, 'targetClass' => MediaFile::class, 'targetAttribute' => ['file_3d_id' => 'id'], 'filter' => ['kind' => MediaFile::KIND_DOCUMENT]],
            [['is_active', 'has_sleeping_place', 'is_foldable'], 'boolean'],
            [['has_sleeping_place', 'is_foldable'], 'default', 'value' => false],
            [['collection_id'], 'exist', 'targetClass' => CatalogCollection::class, 'targetAttribute' => ['collection_id' => 'id']],
            [['category_id'], 'exist', 'targetClass' => CatalogCategory::class, 'targetAttribute' => ['category_id' => 'id']],
            [['subcategory_id'], 'exist', 'skipOnError' => true, 'targetClass' => CatalogSubcategory::class, 'targetAttribute' => ['subcategory_id' => 'id']],
            [['layout_id'], 'exist', 'skipOnError' => true, 'targetClass' => CatalogLayout::class, 'targetAttribute' => ['layout_id' => 'id']],
            [['badge_id'], 'exist', 'skipOnError' => true, 'targetClass' => CatalogBadge::class, 'targetAttribute' => ['badge_id' => 'id']],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'slug' => 'Slug',
            'title' => 'Название',
            'subtitle' => 'Подзаголовок',
            'description' => 'Описание',
            'collection_id' => 'Коллекция',
            'category_id' => 'Категория',
            'subcategory_id' => 'Подкатегория',
            'layout_id' => 'Раскладка',
            'badge_id' => 'Бейдж',
            'video_id' => 'Видео',
            'fitting_room_url' => 'Ссылка на примерочную 3D',
            'polygons_3d' => 'Полигоны для 3D',
            'file_3d_url' => 'URL для загрузки файла 3D',
            'tech_photos_folder_url' => 'Ссылка на диск с тех.фото',
            'file_3d_id' => 'Файл 3D (медиатека)',
            'overall_size' => 'Размер (Ш×В×Г), мм',
            'seat_depth' => 'Гл. посадочного места, мм',
            'seat_height' => 'Выс. посадочного места, мм',
            'armrest_width' => 'Шир. подлокотника, мм',
            'leg_height' => 'Высота опоры, мм',
            'width_mm' => 'Ширина, мм',
            'height_mm' => 'Высота, мм',
            'depth_mm' => 'Глубина, мм',
            'corner_depth_mm' => 'Гл. угла, мм',
            'has_sleeping_place' => 'Спальное место',
            'is_foldable' => 'Раскладной',
            'sleeping_place_width_mm' => 'Ширина, мм',
            'sleeping_place_depth_mm' => 'Глубина мм',
            'frame_spec' => 'Каркас',
            'mechanism' => 'Механизм',
            'frame' => 'Каркас (красивое)',
            'foundation' => 'Основание (красивое)',
            'filling_spec' => 'Наполнение',
            'additional' => 'Дополнительно',
            'filling' => 'Наполнение (красивое)',
            'upholstery' => 'Обивка (красивое)',
            'supports' => 'Опоры (красивое)',
            'sort_order' => 'Порядок',
            'is_active' => 'Активна',
        ];
    }

    public function beforeValidate(): bool
    {
        if ($this->video_id === '' || (int)$this->video_id === 0) {
            $this->video_id = null;
        }
        if ($this->file_3d_id === '' || (int)$this->file_3d_id === 0) {
            $this->file_3d_id = null;
        }
        if ($this->file_3d_id !== null && (int)$this->file_3d_id > 0) {
            $this->file_3d_url = null;
        }

        $this->applyAutoTitleFromHierarchy();
        $this->applyTitleSlug();

        if ($this->subcategory_id && $this->category_id) {
            $sub = CatalogSubcategory::findOne((int)$this->subcategory_id);
            if ($sub !== null && (int)$sub->category_id !== (int)$this->category_id) {
                $this->addError('subcategory_id', 'Подкатегория не принадлежит выбранной категории.');
            }
        }

        CatalogListingValueParser::syncDimensionAttributes($this);
        $this->syncFilterFunctionAttributes();

        return parent::beforeValidate();
    }

    private function syncFilterFunctionAttributes(): void
    {
        $categorySlug = $this->category?->slug;
        if ($categorySlug === null && $this->category_id) {
            $category = CatalogCategory::findOne((int)$this->category_id);
            $categorySlug = $category?->slug;
        }

        if ($categorySlug !== null && !CatalogCategorySlug::isSofa($categorySlug)) {
            $this->has_sleeping_place = false;
        }

        if ($categorySlug !== null && !CatalogCategorySlug::isArmchair($categorySlug)) {
            $this->is_foldable = false;
        }

        if (!$this->has_sleeping_place && !$this->is_foldable) {
            $this->sleeping_place_width_mm = null;
            $this->sleeping_place_depth_mm = null;
        }
    }

    public function applyAutoTitleFromHierarchy(): void
    {
        if (trim((string)$this->title) !== '') {
            return;
        }

        if (!$this->collection_id || !$this->subcategory_id) {
            return;
        }

        $collection = $this->collection ?? CatalogCollection::findOne((int)$this->collection_id);
        $subcategory = $this->subcategory ?? CatalogSubcategory::findOne((int)$this->subcategory_id);
        if ($collection === null || $subcategory === null) {
            return;
        }

        $title = trim($subcategory->label . ' ' . $collection->getDisplayName());
        if ($title !== '') {
            $this->title = $title;
        }
    }

    public function applyTitleSlug(): void
    {
        $titlePart = SlugHelper::slugify((string)$this->title);
        if ($titlePart === '') {
            return;
        }

        $collection = $this->collection
            ?? ($this->collection_id ? CatalogCollection::findOne((int)$this->collection_id) : null);
        $collectionSlug = $collection !== null ? trim((string)$collection->slug) : '';

        $base = $collectionSlug !== '' ? $collectionSlug . '-' . $titlePart : $titlePart;
        $this->slug = $this->ensureUniqueSlug($base);
    }

    private function ensureUniqueSlug(string $base): string
    {
        $slug = $base;
        $suffix = 2;

        while ($this->isSlugTaken($slug)) {
            $slug = $base . '-' . $suffix;
            $suffix++;
        }

        return substr($slug, 0, 64);
    }

    private function isSlugTaken(string $slug): bool
    {
        $query = self::find()->where(['slug' => $slug]);
        if (!$this->isNewRecord) {
            $query->andWhere(['<>', 'id', $this->id]);
        }

        return $query->exists();
    }

    public function getCollection()
    {
        return $this->hasOne(CatalogCollection::class, ['id' => 'collection_id']);
    }

    public function getCategory()
    {
        return $this->hasOne(CatalogCategory::class, ['id' => 'category_id']);
    }

    public function getSubcategory()
    {
        return $this->hasOne(CatalogSubcategory::class, ['id' => 'subcategory_id']);
    }

    public function getLayout()
    {
        return $this->hasOne(CatalogLayout::class, ['id' => 'layout_id']);
    }

    public function getBadge()
    {
        return $this->hasOne(CatalogBadge::class, ['id' => 'badge_id']);
    }

    public function getVideo()
    {
        return $this->hasOne(MediaFile::class, ['id' => 'video_id']);
    }

    public function getFile3d()
    {
        return $this->hasOne(MediaFile::class, ['id' => 'file_3d_id']);
    }

    public function resolveFile3dPublicUrl(): ?string
    {
        if (!$this->hasAttribute('file_3d_id') || (int)$this->file_3d_id <= 0) {
            return null;
        }

        if ($this->file3d === null) {
            $this->populateRelation('file3d', MediaFile::findOne((int)$this->file_3d_id));
        }

        if ($this->file3d === null) {
            return null;
        }

        $url = trim($this->file3d->getPublicUrl());

        return $url !== '' ? $url : null;
    }

    public function getModelImages()
    {
        return $this->hasMany(CatalogModelImage::class, ['model_id' => 'id'])
            ->andWhere(['purpose' => CatalogModelImage::PURPOSE_ANGLE])
            ->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC]);
    }

    public function getModelInteriorImages()
    {
        return $this->hasMany(CatalogModelImage::class, ['model_id' => 'id'])
            ->andWhere(['purpose' => CatalogModelImage::PURPOSE_INTERIOR])
            ->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC]);
    }

    public function getAllModelImages()
    {
        return $this->hasMany(CatalogModelImage::class, ['model_id' => 'id'])
            ->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC]);
    }

    public function getModelDimensionImages()
    {
        return $this->hasMany(CatalogModelDimensionImage::class, ['model_id' => 'id'])
            ->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC]);
    }

    public function getModelPrices()
    {
        return $this->hasMany(CatalogModelPrice::class, ['model_id' => 'id'])
            ->with('priceCategory')
            ->orderBy(['price_category_id' => SORT_ASC]);
    }

    public function getPriceCategoryLinks()
    {
        return $this->hasMany(CatalogModelPriceCategoryLink::class, ['model_id' => 'id'])
            ->orderBy(['sort_order' => SORT_ASC, 'price_category_id' => SORT_ASC]);
    }

    /**
     * @return int[]
     */
    public static function getDefaultPriceCategoryIds(): array
    {
        $categories = CatalogPriceCategory::findActiveOrdered();

        return array_map(
            static fn (CatalogPriceCategory $category): int => (int)$category->id,
            array_slice($categories, 0, CatalogPriceCategory::DEFAULT_COUNT)
        );
    }

    public function seedDefaultPriceCategoryLinks(): void
    {
        if (CatalogModelPriceCategoryLink::find()->where(['model_id' => $this->id])->exists()) {
            return;
        }

        $this->syncPriceCategoryLinks(self::getDefaultPriceCategoryIds());
    }

    /**
     * @param int[] $categoryIds
     */
    public function syncPriceCategoryLinks(array $categoryIds): void
    {
        $categoryIds = array_values(array_unique(array_filter(array_map('intval', $categoryIds))));

        $existing = CatalogModelPriceCategoryLink::find()
            ->where(['model_id' => $this->id])
            ->indexBy('price_category_id')
            ->all();

        foreach ($existing as $categoryId => $link) {
            if (!in_array((int)$categoryId, $categoryIds, true)) {
                $link->delete();
            }
        }

        $now = date('Y-m-d H:i:s');
        foreach ($categoryIds as $sortOrder => $categoryId) {
            $link = $existing[$categoryId] ?? null;
            if ($link === null) {
                $link = new CatalogModelPriceCategoryLink([
                    'model_id' => $this->id,
                    'price_category_id' => $categoryId,
                    'sort_order' => $sortOrder,
                    'created_at' => $now,
                ]);
                $link->save(false);
                continue;
            }

            if ((int)$link->sort_order !== $sortOrder) {
                $link->sort_order = $sortOrder;
                $link->save(false, ['sort_order']);
            }
        }
    }

    public function getProducts()
    {
        return $this->hasMany(CatalogProduct::class, ['model_id' => 'id']);
    }

    public function getCustomProduct()
    {
        return $this->hasOne(CatalogProduct::class, ['model_id' => 'id'])
            ->andWhere(['fabric_color_id' => null]);
    }

    public function getCustomProductSlug(): ?string
    {
        if ($this->isRelationPopulated('products')) {
            foreach ($this->products as $product) {
                if ($product->fabric_color_id === null) {
                    return $product->slug;
                }
            }

            return null;
        }

        $product = $this->customProduct;

        return $product !== null ? $product->slug : null;
    }

    public function getFabricCollections()
    {
        return $this->hasMany(CatalogFabricCollection::class, ['id' => 'fabric_collection_id'])
            ->viaTable('{{%catalog_model_fabric_collections}}', ['model_id' => 'id']);
    }

    /**
     * Активные цвета тканей из связанных коллекций (для списка моделей и превью SKU).
     *
     * @return CatalogFabricColor[]
     */
    public function getLinkedActiveFabricColors(): array
    {
        $colors = [];
        $seen = [];

        foreach ($this->fabricCollections as $fabricCollection) {
            if (!$fabricCollection->is_active) {
                continue;
            }
            foreach ($fabricCollection->activeColors as $color) {
                $colorId = (int)$color->id;
                if (isset($seen[$colorId])) {
                    continue;
                }
                $seen[$colorId] = true;
                $colors[] = $color;
            }
        }

        return $colors;
    }

    /**
     * Уникальные оттенки модели для карточки листинга.
     *
     * @return array{swatches: list<array<string, mixed>>, swatchCount: int}
     */
    public function collectListingSwatchesPayload(int $previewLimit = 3): array
    {
        $allColors = $this->getLinkedActiveFabricColors();
        if (count($allColors) <= $previewLimit) {
            $swatches = [];
            foreach ($allColors as $fabricColor) {
                $swatches[] = $fabricColor->toListingSwatchPayload();
            }

            return [
                'swatches' => $swatches,
                'swatchCount' => count($allColors),
            ];
        }

        $uniqueShades = [];
        foreach ($allColors as $fabricColor) {
            $shadeKey = $fabricColor->color_id !== null
                ? 'cc:' . (int)$fabricColor->color_id
                : 'fc:' . (int)$fabricColor->id;
            if (isset($uniqueShades[$shadeKey])) {
                continue;
            }
            $uniqueShades[$shadeKey] = $fabricColor;
        }

        $shades = array_values($uniqueShades);
        $swatches = [];
        foreach (array_slice($shades, 0, max(0, $previewLimit)) as $fabricColor) {
            $swatches[] = $fabricColor->toListingSwatchPayload();
        }

        return [
            'swatches' => $swatches,
            'swatchCount' => count($shades),
        ];
    }

    /**
     * @return array<int, string|null> price category number => price_display
     */
    public function getPriceMap(): array
    {
        $map = [];
        foreach ($this->modelPrices as $price) {
            $number = $price->priceCategory?->number;
            if ($number !== null) {
                $map[(int)$number] = $price->price_display;
            }
        }

        return $map;
    }

    public function getPriceForPriceCategoryId(int $priceCategoryId): ?string
    {
        foreach ($this->modelPrices as $price) {
            if ((int)$price->price_category_id === $priceCategoryId) {
                return $price->price_display;
            }
        }

        return null;
    }

    /**
     * @return array<string, string|null>|null
     */
    public function getDimensionsApiPayload(): ?array
    {
        $values = [
            'overallSize' => $this->overall_size,
            'seatDepth' => $this->seat_depth,
            'seatHeight' => $this->seat_height,
            'armrestWidth' => $this->armrest_width,
            'legHeight' => $this->leg_height,
            'width' => $this->width_mm !== null ? (int)$this->width_mm : null,
            'height' => $this->height_mm !== null ? (int)$this->height_mm : null,
            'depth' => $this->depth_mm !== null ? (int)$this->depth_mm : null,
            'cornerDepth' => $this->corner_depth_mm !== null ? (int)$this->corner_depth_mm : null,
            'sleepingPlaceWidth' => $this->sleeping_place_width_mm !== null ? (int)$this->sleeping_place_width_mm : null,
            'sleepingPlaceDepth' => $this->sleeping_place_depth_mm !== null ? (int)$this->sleeping_place_depth_mm : null,
            'unit' => 'mm',
        ];

        foreach ($values as $key => $value) {
            if ($key === 'unit') {
                continue;
            }
            if ($value !== null && $value !== '') {
                return $values;
            }
        }

        return null;
    }

    /**
     * @return array<string, string|null>|null
     */
    public function getMaterialsApiPayload(): ?array
    {
        $values = [
            'frameSpec' => $this->frame_spec,
            'mechanism' => $this->mechanism,
            'fillingSpec' => $this->filling_spec,
            'additional' => $this->additional,
            'frame' => $this->frame,
            'foundation' => $this->foundation,
            'filling' => $this->filling,
            'upholstery' => $this->upholstery,
            'supports' => $this->supports,
        ];

        foreach ($values as $value) {
            if ($value !== null && $value !== '') {
                return $values;
            }
        }

        return null;
    }

    /**
     * @return array<int, array{src: string, alt: string}>
     */
    public function getImagesApiPayload(): array
    {
        return $this->buildImagesPayloadFromLinks($this->modelImages);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getFirstImageApiPayload(): ?array
    {
        foreach ($this->modelImages as $link) {
            if ($link->media === null) {
                continue;
            }

            $alt = $link->media->alt ?? $link->media->filename;

            return $link->media->toApiImagePayload($alt);
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getListingImagesApiPayload(): array
    {
        return $this->buildImagesPayloadFromLinks($this->modelImages, forListing: true);
    }

    /**
     * @return array<int, array{src: string, alt: string}>
     */
    public function getInteriorImagesApiPayload(): array
    {
        return $this->buildImagesPayloadFromLinks($this->modelInteriorImages);
    }

    /**
     * Карточка модели для picker мудборда (ракурсы + «Фото для мудборда» из админки).
     *
     * @return array<string, mixed>
     */
    public function toMoodboardPickerApiItem(): array
    {
        $angles = $this->getImagesApiPayload();
        $moodboardPhotos = $this->getInteriorImagesApiPayload();

        return [
            'slug' => $this->slug,
            'title' => $this->title,
            'collection' => $this->collection !== null ? [
                'slug' => $this->collection->slug,
                'title' => $this->collection->getDisplayName(),
            ] : null,
            'category' => $this->category !== null ? [
                'slug' => $this->category->slug,
                'label' => $this->category->label,
            ] : null,
            'angles' => $angles,
            'previewImage' => $angles[0] ?? null,
            'moodboardPhotos' => $moodboardPhotos,
        ];
    }

    /**
     * @param CatalogModelImage[] $links
     * @return array<int, array{src: string, alt: string}>
     */
    private function buildImagesPayloadFromLinks(array $links, bool $forListing = false): array
    {
        $images = [];
        foreach ($links as $link) {
            if ($link->media === null) {
                continue;
            }
            $alt = $link->media->alt ?? $link->media->filename;
            $images[] = $forListing
                ? $link->media->toListingApiImagePayload($alt)
                : $link->media->toApiImagePayload($alt);
        }

        return $images;
    }

    /**
     * @return array<int, array{src: string, alt: string}>
     */
    public function getDimensionImagesApiPayload(): array
    {
        $images = [];
        foreach ($this->modelDimensionImages as $link) {
            if ($link->media === null) {
                continue;
            }
            $images[] = $link->media->toApiImagePayload($link->media->alt ?? $link->media->filename);
        }

        return $images;
    }

    /**
     * Полный payload модели для API (поля формы модели + медиа + матрица цен).
     *
     * @return array<string, mixed>
     */
    public function toCatalogApiPayload(): array
    {
        $badge = null;
        if ($this->badge !== null) {
            $badge = [
                'text' => $this->badge->label,
                'variant' => $this->badge->variant,
            ];
            if ($this->badge->image !== null) {
                $badge['image'] = $this->badge->image->toApiImagePayload($this->badge->image->alt ?? $this->badge->label);
            }
        }

        $video = $this->video;
        $interiorImages = $this->getInteriorImagesApiPayload();
        $dimensionImages = $this->getDimensionImagesApiPayload();

        $prices = [];
        foreach ($this->getPriceMap() as $category => $display) {
            if ($display !== null && trim((string)$display) !== '') {
                $prices[(string)$category] = $display;
            }
        }

        return [
            'id' => $this->slug,
            'title' => $this->title,
            'subtitle' => $this->subtitle ?: null,
            'description' => $this->description ?: null,
            'type' => $this->subcategory?->label,
            'typeId' => $this->subcategory?->slug,
            'category' => $this->category?->slug,
            'categoryLabel' => $this->category?->label,
            'subcategory' => $this->subcategory?->slug,
            'collection' => $this->collection?->getDisplayName(),
            'customProductSlug' => $this->getCustomProductSlug(),
            'layout' => $this->layout?->slug,
            'badge' => $badge,
            'video' => $video ? [
                'src' => $video->getPublicUrl(),
                'mime' => $video->mime,
            ] : null,
            'fittingRoomUrl' => $this->fitting_room_url ?: null,
            'dimensions' => $this->getDimensionsApiPayload(),
            'materials' => $this->getMaterialsApiPayload(),
            'modelImages' => $this->getImagesApiPayload(),
            'modelInteriorImages' => $interiorImages !== [] ? $interiorImages : null,
            'dimensionImages' => $dimensionImages !== [] ? $dimensionImages : null,
            'techPhotosFolderUrl' => $this->tech_photos_folder_url ?: null,
            'prices' => $prices !== [] ? $prices : null,
        ];
    }

    public function syncFabricCollectionLinks(array $fabricCollectionIds): void
    {
        $fabricCollectionIds = array_values(array_unique(array_filter(array_map('intval', $fabricCollectionIds))));

        $existing = \Yii::$app->db->createCommand(
            'SELECT fabric_collection_id FROM {{%catalog_model_fabric_collections}} WHERE model_id = :id',
            ['id' => $this->id]
        )->queryColumn();

        $toDelete = array_diff($existing, $fabricCollectionIds);
        foreach ($toDelete as $fabricCollectionId) {
            \Yii::$app->db->createCommand()->delete(
                '{{%catalog_model_fabric_collections}}',
                ['model_id' => $this->id, 'fabric_collection_id' => $fabricCollectionId]
            )->execute();
        }

        $now = date('Y-m-d H:i:s');
        foreach ($fabricCollectionIds as $fabricCollectionId) {
            if (in_array($fabricCollectionId, $existing, true)) {
                continue;
            }
            \Yii::$app->db->createCommand()->insert('{{%catalog_model_fabric_collections}}', [
                'model_id' => $this->id,
                'fabric_collection_id' => $fabricCollectionId,
                'created_at' => $now,
            ])->execute();
        }
    }

    /**
     * @param array<int|string, mixed> $postedRows
     * @return array<int, string> price_category_id => price_display
     */
    public static function normalizePostedPrices(array $postedRows): array
    {
        $result = [];

        foreach ($postedRows as $key => $value) {
            if (is_array($value)) {
                $categoryId = (int)($value['category_id'] ?? 0);
                $display = trim((string)($value['price_display'] ?? ''));
            } else {
                $categoryId = (int)$key;
                $display = trim((string)$value);
            }

            if ($categoryId <= 0) {
                continue;
            }

            $result[$categoryId] = $display;
        }

        return $result;
    }

    /**
     * @param array<int, string> $pricesByCategoryId
     */
    public function syncPrices(array $pricesByCategoryId): void
    {
        $postedCategoryIds = array_map('intval', array_keys($pricesByCategoryId));

        $existingPrices = CatalogModelPrice::find()
            ->where(['model_id' => $this->id])
            ->all();

        foreach ($existingPrices as $price) {
            if (!in_array((int)$price->price_category_id, $postedCategoryIds, true)) {
                $price->delete();
            }
        }

        foreach ($pricesByCategoryId as $categoryId => $display) {
            $categoryId = (int)$categoryId;
            $display = trim((string)$display);
            if ($display === '') {
                continue;
            }

            $price = CatalogModelPrice::findOne([
                'model_id' => $this->id,
                'price_category_id' => $categoryId,
            ]);

            if ($price === null) {
                $price = new CatalogModelPrice([
                    'model_id' => $this->id,
                    'price_category_id' => $categoryId,
                ]);
            }
            $price->price_display = $display;
            $price->save(false);
        }
    }

    /**
     * @param array<int|string, mixed> $postedRows
     */
    public function validatePricesComplete(array $postedRows): ?string
    {
        $categoryIds = [];
        $hasValidRow = false;

        foreach ($postedRows as $key => $row) {
            if (is_array($row)) {
                $categoryId = (int)($row['category_id'] ?? 0);
                $display = trim((string)($row['price_display'] ?? ''));
            } else {
                $categoryId = (int)$key;
                $display = trim((string)$row);
            }

            if ($categoryId <= 0 && $display === '') {
                continue;
            }

            if ($categoryId <= 0) {
                return 'Выберите категорию ткани для каждой строки.';
            }

            if (isset($categoryIds[$categoryId])) {
                $label = CatalogPriceCategory::findOne($categoryId)?->getDisplayLabel() ?? 'категория';

                return 'Категория «' . $label . '» указана более одного раза.';
            }

            $priceCategory = CatalogPriceCategory::findOne(['id' => $categoryId, 'is_active' => true]);
            if ($priceCategory === null) {
                return 'Выбрана недоступная ценовая категория.';
            }

            if ($display === '') {
                return 'Заполните цену для категории «' . $priceCategory->getDisplayLabel() . '».';
            }

            $categoryIds[$categoryId] = true;
            $hasValidRow = true;
        }

        if (!$hasValidRow) {
            return 'Добавьте хотя бы одну ценовую категорию.';
        }

        return null;
    }
}
