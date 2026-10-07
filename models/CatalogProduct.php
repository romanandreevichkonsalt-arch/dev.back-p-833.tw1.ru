<?php

namespace app\models;

use app\models\MediaFile;
use app\models\traits\AutoSlugTrait;
use app\services\catalog\CatalogListingValueParser;
use app\services\catalog\CatalogUrlSlugResolver;
use app\services\dealer\DealerPricingService;
use app\services\promotion\CatalogPromotionPricing;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

class CatalogProduct extends ActiveRecord
{
    use AutoSlugTrait;

    private const LISTING_GALLERY_MAX = 6;

    /** One image is enough for autocomplete cards; gallery bloated FileCache (~52MB). */
    private const SEARCH_INDEX_GALLERY_MAX = 1;

    protected function slugSourceAttribute(): string
    {
        return 'title';
    }

    public static function tableName(): string
    {
        return '{{%catalog_products}}';
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
            [['slug', 'title', 'href'], 'required'],
            [['slug'], 'string', 'max' => 64],
            [['slug'], 'unique'],
            [['title', 'subtitle'], 'string', 'max' => 255],
            [['description'], 'string'],
            [['price_display', 'image_position'], 'string', 'max' => 64],
            [['overall_size', 'seat_depth', 'seat_height', 'armrest_width', 'leg_height'], 'string', 'max' => 64],
            [['price_amount', 'width_mm', 'height_mm', 'depth_mm', 'corner_depth_mm', 'sleeping_place_width_mm', 'sleeping_place_depth_mm'], 'integer'],
            [['frame', 'frame_spec', 'mechanism', 'foundation', 'filling', 'filling_spec', 'additional', 'upholstery', 'supports'], 'string'],
            [['href'], 'string', 'max' => 512],
            [[
                'subcategory_id',
                'collection_id',
                'model_id',
                'fabric_color_id',
                'badge_id',
                'layout_id',
                'image_id',
                'video_id',
                'sort_order',
            ], 'integer'],
            [['video_id'], 'exist', 'skipOnError' => true, 'targetClass' => MediaFile::class, 'targetAttribute' => ['video_id' => 'id'], 'filter' => ['kind' => MediaFile::KIND_VIDEO]],
            [['is_active', 'is_custom', 'has_sleeping_place', 'is_foldable', 'is_popular'], 'boolean'],
            [['is_custom', 'has_sleeping_place', 'is_foldable', 'is_popular'], 'default', 'value' => false],
            [['quantity'], 'integer', 'min' => 0],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'slug' => 'Slug',
            'title' => 'Название',
            'subtitle' => 'Подзаголовок',
            'description' => 'Описание',
            'subcategory_id' => 'Подкатегория',
            'collection_id' => 'Коллекция',
            'model_id' => 'Модель',
            'fabric_color_id' => 'Цвет ткани',
            'price_display' => 'Цена (отображение)',
            'price_amount' => 'Цена (число, ₽)',
            'width_mm' => 'Ширина, мм',
            'height_mm' => 'Высота, мм',
            'depth_mm' => 'Глубина, мм',
            'corner_depth_mm' => 'Гл. угла, мм',
            'has_sleeping_place' => 'Спальное место',
            'is_foldable' => 'Раскладной',
            'sleeping_place_width_mm' => 'Ширина, мм',
            'sleeping_place_depth_mm' => 'Глубина мм',
            'quantity' => 'Кол-во на складе',
            'href' => 'Ссылка',
            'image_id' => 'Изображение',
            'video_id' => 'Видео',
            'badge_id' => 'Бейдж',
            'layout_id' => 'Раскладка',
            'overall_size' => 'Размер (Ш×В×Г), мм',
            'seat_depth' => 'Глубина посадочного места, мм',
            'seat_height' => 'Высота посадочного места, мм',
            'armrest_width' => 'Ширина подлокотника, мм',
            'leg_height' => 'Высота опоры, мм',
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
            'is_active' => 'Активен',
            'is_popular' => 'Популярный товар',
        ];
    }

    public function getSubcategory()
    {
        return $this->hasOne(CatalogSubcategory::class, ['id' => 'subcategory_id']);
    }

    public function getCollection()
    {
        return $this->hasOne(CatalogCollection::class, ['id' => 'collection_id']);
    }

    public function getCatalogModel()
    {
        return $this->hasOne(CatalogModel::class, ['id' => 'model_id']);
    }

    public function getFabricColor()
    {
        return $this->hasOne(CatalogFabricColor::class, ['id' => 'fabric_color_id']);
    }

    public function getBadge()
    {
        return $this->hasOne(CatalogBadge::class, ['id' => 'badge_id']);
    }

    public function getLayout()
    {
        return $this->hasOne(CatalogLayout::class, ['id' => 'layout_id']);
    }

    public function getImage()
    {
        return $this->hasOne(MediaFile::class, ['id' => 'image_id']);
    }

    public function getVideo()
    {
        return $this->hasOne(MediaFile::class, ['id' => 'video_id']);
    }

    public function isGeneratedFromModel(): bool
    {
        return $this->model_id !== null && (int)$this->model_id > 0;
    }

    public function getListingGroupKey(): int
    {
        return (int)$this->model_id > 0 ? (int)$this->model_id : -1 * (int)$this->id;
    }

    public function getListingGroupSort(): string
    {
        return sprintf(
            '%010d-%010d-%010d',
            (int)($this->collection->sort_order ?? 0),
            (int)($this->catalogModel->sort_order ?? $this->sort_order ?? 0),
            (int)($this->model_id ?? 0)
        );
    }

    public function getListingIntraSort(): string
    {
        return sprintf(
            '%010d-%010d-%010d',
            (int)($this->fabricColor?->fabricCollection?->sort_order ?? 0),
            (int)($this->fabricColor?->sort_order ?? 0),
            (int)$this->id
        );
    }

    public function beforeValidate(): bool
    {
        if ($this->video_id === '' || (int)$this->video_id === 0) {
            $this->video_id = null;
        }

        if (!$this->isGeneratedFromModel()) {
            $this->applyAutoSlug();

            if (trim((string)$this->href) === '' && trim((string)$this->slug) !== '') {
                $collection = $this->collection ?? CatalogCollection::findOne($this->collection_id);
                if ($collection !== null && trim((string)$collection->href) !== '') {
                    $this->href = rtrim($collection->href, '/') . '/' . $this->slug;
                } else {
                    $this->href = '/catalog/' . $this->slug;
                }
            }
        }

        if (!$this->isGeneratedFromModel()) {
            CatalogListingValueParser::syncDimensionAttributes($this);
        }

        return parent::beforeValidate();
    }

    /**
     * @return array<string, string|null>|null
     */
    public function getDimensionsApiPayload(): ?array
    {
        if ($this->isGeneratedFromModel() && $this->catalogModel !== null) {
            return $this->catalogModel->getDimensionsApiPayload();
        }

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
            'unit' => 'mm',
        ];

        $hasValue = false;
        foreach ($values as $key => $value) {
            if ($key === 'unit') {
                continue;
            }
            if ($value !== null && $value !== '') {
                $hasValue = true;
                break;
            }
        }

        return $hasValue ? $values : null;
    }

    /**
     * @return array<string, string|null>|null
     */
    public function getMaterialsApiPayload(): ?array
    {
        if ($this->isGeneratedFromModel() && $this->catalogModel !== null) {
            return $this->catalogModel->getMaterialsApiPayload();
        }

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

        $hasValue = false;
        foreach ($values as $value) {
            if ($value !== null && $value !== '') {
                $hasValue = true;
                break;
            }
        }

        return $hasValue ? $values : null;
    }

    public function resolvePrimaryImagePayload(bool $forListing = false): array
    {
        $buildPayload = static fn (MediaFile $media, string $alt): array => $forListing
            ? $media->toListingApiImagePayload($alt)
            : $media->toProductDetailApiImagePayload($alt);

        if ($this->image !== null) {
            $payload = $buildPayload($this->image, $this->image->alt ?? $this->title);
            if (($payload['src'] ?? null) !== null && $payload['src'] !== '') {
                return $payload;
            }
        }

        if ($this->isGeneratedFromModel() && $this->catalogModel !== null) {
            if ($forListing) {
                $listingImages = $this->catalogModel->getListingImagesApiPayload();
                if ($listingImages !== []) {
                    return $listingImages[0];
                }
            } else {
                $firstImage = $this->catalogModel->getFirstImageApiPayload();
                if ($firstImage !== null) {
                    return $firstImage;
                }
            }
        }

        if ($this->collection?->image !== null) {
            $payload = $buildPayload(
                $this->collection->image,
                $this->collection->image->alt ?? $this->collection->getDisplayName()
            );
            if (($payload['src'] ?? null) !== null && $payload['src'] !== '') {
                return $payload;
            }
        }

        return MediaFile::emptyImagePayload($this->title);
    }

    /**
     * Публичные поля цены для API (каталог, корзина, избранное, карточка).
     * retailPrice — розница каталога; dealerPrice — со скидкой дилера или по акции (только Bearer дилера).
     *
     * @return array{
     *     retailPrice: ?int,
     *     priceDisplay: ?string,
     *     dealerPrice?: int,
     *     dealerDiscountPercent?: ?int,
     *     catalogPromotion?: array<string, mixed>
     * }
     */
    public function buildPricingApiPayload(?User $dealer = null): array
    {
        $pricingService = new DealerPricingService();
        $prices = $pricingService->buildProductPrices($this, $dealer);
        $catalogRetail = $prices['retailPrice'] ?? null;

        $payload = [
            'retailPrice' => $catalogRetail,
            'priceDisplay' => $catalogRetail !== null
                ? $pricingService->formatPriceDisplay($catalogRetail)
                : ($this->price_display ?: null),
        ];

        if (isset($prices['dealerPrice'])) {
            $payload['dealerPrice'] = $prices['dealerPrice'];
        }

        if (isset($prices['dealerDiscountPercent'])) {
            $payload['dealerDiscountPercent'] = $prices['dealerDiscountPercent'];
        }
        if (isset($prices['catalogPromotion'])) {
            $payload['catalogPromotion'] = $prices['catalogPromotion'];
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $target
     * @param array<string, mixed> $pricing
     */
    public static function applyPricingApiFields(array $target, array $pricing): array
    {
        foreach (['retailPrice', 'priceDisplay', 'dealerPrice', 'dealerDiscountPercent', 'catalogPromotion'] as $key) {
            if (array_key_exists($key, $pricing)) {
                $target[$key] = $pricing[$key];
            }
        }

        return $target;
    }

    /**
     * @param array<string, mixed> $target
     * @param array<string, mixed> $pricing
     */
    public static function applyListingPricingApiFields(array $target, array $pricing): array
    {
        foreach (['retailPrice', 'dealerPrice', 'dealerDiscountPercent', 'catalogPromotion'] as $key) {
            if (array_key_exists($key, $pricing)) {
                $target[$key] = $pricing[$key];
            }
        }

        return $target;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function buildApiBadgesPayload(?User $dealer = null): array
    {
        $badges = [];

        if ($dealer !== null && $dealer->isDealer()) {
            $prices = (new DealerPricingService())->buildProductPrices($this, $dealer);
            if (isset($prices['catalogPromotion'])) {
                $badges[] = CatalogPromotionPricing::promotionBadgePayload();
            }
        }

        if ($this->badge !== null) {
            $catalogBadge = [
                'text' => $this->badge->label,
                'variant' => $this->badge->variant,
            ];
            if ($this->badge->image !== null) {
                $catalogBadge['image'] = $this->badge->image->toApiImagePayload(
                    $this->badge->image->alt ?? $this->badge->label
                );
            }
            $badges[] = $catalogBadge;
        }

        return $badges;
    }

    public function toMenuApiItem(?User $dealer = null): array
    {
        $badges = $this->buildApiBadgesPayload($dealer);
        $badge = $badges[0] ?? null;

        $video = $this->video;
        $modelImages = null;
        $modelInteriorImages = null;
        $dimensionImages = null;
        if ($this->isGeneratedFromModel() && $this->catalogModel !== null) {
            $video = $this->catalogModel->video ?? $video;
            $modelImages = $this->catalogModel->getImagesApiPayload();
            $interior = $this->catalogModel->getInteriorImagesApiPayload();
            if ($interior !== []) {
                $modelInteriorImages = $interior;
            }
            $dimensionImages = $this->catalogModel->getDimensionImagesApiPayload();
        }

        $fabricColor = $this->fabricColor !== null ? $this->fabricColor->toApiPayload() : null;

        $item = [
            'id' => $this->slug,
            'title' => $this->title,
            'subtitle' => $this->subtitle ?: null,
            'description' => $this->description ?: null,
            'type' => $this->subcategory?->label,
            'collection' => $this->collection?->getDisplayName(),
            'subcategory' => $this->subcategory?->slug,
            'href' => $this->href,
            'image' => $this->resolvePrimaryImagePayload(),
            'video' => $video ? [
                'src' => $video->getPublicUrl(),
                'mime' => $video->mime,
            ] : null,
            'imagePosition' => $this->image_position ?? 'center',
            'badge' => $badge,
            'badges' => $badges !== [] ? $badges : null,
            'layout' => $this->layout?->slug,
            'dimensions' => $this->getDimensionsApiPayload(),
            'materials' => $this->getMaterialsApiPayload(),
            'quantity' => (int)$this->quantity,
            'inStock' => (bool)$this->is_active && (int)$this->quantity > 0,
            'custom' => (bool)$this->is_custom,
        ];

        if ($this->isGeneratedFromModel() && $this->catalogModel !== null) {
            $item['modelId'] = $this->catalogModel->slug;
            $item['model'] = $this->catalogModel->toCatalogApiPayload();
            $item['fittingRoomUrl'] = $this->catalogModel->fitting_room_url ?: null;
            $techPhotosFolderUrl = trim((string)$this->catalogModel->tech_photos_folder_url);
            if ($techPhotosFolderUrl !== '') {
                $item['techPhotosFolderUrl'] = $techPhotosFolderUrl;
            }
        }

        if ($modelImages !== null) {
            $item['modelImages'] = $modelImages;
        }
        if ($modelInteriorImages !== null) {
            $item['modelInteriorImages'] = $modelInteriorImages;
        }
        if ($dimensionImages !== null && $dimensionImages !== []) {
            $item['dimensionImages'] = $dimensionImages;
        }
        if ($fabricColor !== null) {
            $item['fabricColor'] = $fabricColor;
        }

        $pricing = $this->buildPricingApiPayload($dealer);
        $item = self::applyPricingApiFields($item, $pricing);
        $item['price'] = $pricing['priceDisplay'];

        return $item;
    }

    /**
     * Карточка товара для листинга каталога и поиска.
     *
     * @return array<string, mixed>
     */
    /**
     * Сокращённая карточка для библиотеки 3D: одна SKU на коллекцию мебели.
     *
     * @return array<string, mixed>
     */
    public function toLibraryApiItem(): array
    {
        $slugResolver = new CatalogUrlSlugResolver();
        $badge = null;
        if ($this->badge !== null) {
            $badge = [
                'text' => $this->badge->label,
                'variant' => $this->badge->variant,
            ];
            if ($this->badge->image !== null) {
                $badge['image'] = $this->badge->image->toApiImagePayload(
                    $this->badge->image->alt ?? $this->badge->label
                );
            }
        }

        $direction = null;
        if ($this->collection?->direction !== null) {
            $direction = [
                'slug' => $slugResolver->getDirectionPublicSlug($this->collection->direction),
                'label' => (string)$this->collection->direction->label,
            ];
        }

        $category = null;
        if ($this->subcategory?->category !== null) {
            $categoryEntity = $this->subcategory->category;
            $category = [
                'slug' => $slugResolver->getCategoryPublicSlug($categoryEntity),
                'label' => (string)$categoryEntity->label,
            ];
        }

        $subcategory = null;
        if ($this->subcategory !== null) {
            $subcategory = [
                'slug' => $slugResolver->getSubcategoryPublicSlug($this->subcategory),
                'label' => (string)$this->subcategory->label,
            ];
        }

        $collection = null;
        if ($this->collection !== null) {
            $collection = [
                'slug' => (string)$this->collection->slug,
                'label' => (string)$this->collection->label,
                'title' => (string)$this->collection->title,
            ];
        }

        $polygons3d = null;
        $file3dUrl = null;
        if ($this->isGeneratedFromModel() && $this->catalogModel !== null) {
            $model = $this->catalogModel;
            if ($model->hasAttribute('polygons_3d')) {
                $polygonsRaw = trim((string)$model->polygons_3d);
                $polygons3d = $polygonsRaw !== '' ? $polygonsRaw : null;
            }
            $file3dUrl = $model->resolveFile3dPublicUrl();
        }

        return [
            'direction' => $direction,
            'category' => $category,
            'subcategory' => $subcategory,
            'collection' => $collection,
            'image' => $this->resolvePrimaryImagePayload(),
            'polygons_3d' => $polygons3d,
            'file_3d_url' => $file3dUrl,
            'badge' => $badge,
        ];
    }

    public function toListingCard(?User $dealer = null): array
    {
        $slugResolver = new CatalogUrlSlugResolver();
        $badges = $this->buildApiBadgesPayload($dealer);
        $badge = $this->normalizeListingBadge($badges[0] ?? null);

        $swatchPayload = $this->catalogModel !== null
            ? $this->catalogModel->collectListingSwatchesPayload()
            : ['swatches' => [], 'swatchCount' => 0];

        $pricing = $this->buildPricingApiPayload($dealer);

        return self::applyListingPricingApiFields([
            'slug' => $this->slug,
            'id' => $this->slug,
            'name' => $this->title,
            'fabric' => $this->fabricColor?->getApiLabel(),
            'badge' => $badge,
            'image' => $this->resolvePrimaryImagePayload(forListing: true),
            'images' => $this->collectListingImages(),
            'swatches' => $swatchPayload['swatches'],
            'swatchCount' => $swatchPayload['swatchCount'],
            'href' => $slugResolver->buildProductUrl((string)$this->slug),
        ], $pricing);
    }

    /**
     * Документ для кэша searchable-products: без swatches и прочих полей листинга каталога.
     *
     * @return array<string, mixed>
     */
    public function toSearchIndexDocument(?User $dealer = null): array
    {
        $badges = $this->buildApiBadgesPayload($dealer);
        $badge = $badges[0] ?? null;
        $pricing = $this->buildPricingApiPayload($dealer);

        $item = self::applyPricingApiFields([
            'slug' => $this->slug,
            'id' => $this->slug,
            'title' => $this->title,
            'subtitle' => $this->subtitle ?: null,
            'description' => $this->description ?: null,
            'type' => $this->subcategory?->label,
            'collection' => $this->collection?->getDisplayName(),
            'image' => $this->resolvePrimaryImagePayload(forListing: true),
            'images' => $this->collectSearchIndexImages(),
            'badge' => $badge,
        ], $pricing);

        return $this->finalizeSearchDocument($item, $dealer, $pricing);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function collectListingImages(): array
    {
        $images = [];
        if ($this->image !== null) {
            $images[] = $this->image->toListingApiImagePayload($this->image->alt ?? $this->title);
        }

        if ($this->isGeneratedFromModel() && $this->catalogModel !== null) {
            foreach ($this->catalogModel->getListingImagesApiPayload() as $image) {
                $images[] = $image;
            }
        }

        return $this->capUniqueImagePayloads($images, self::LISTING_GALLERY_MAX);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function collectSearchIndexImages(): array
    {
        $images = [];
        if ($this->image !== null) {
            $images[] = $this->image->toListingApiImagePayload($this->image->alt ?? $this->title);
        }

        if ($this->isGeneratedFromModel() && $this->catalogModel !== null) {
            foreach ($this->catalogModel->getListingImagesApiPayload() as $image) {
                if (count($images) >= self::SEARCH_INDEX_GALLERY_MAX) {
                    break;
                }
                $images[] = $image;
            }
        }

        return $this->capUniqueImagePayloads($images, self::SEARCH_INDEX_GALLERY_MAX);
    }

    /**
     * @param list<array<string, mixed>> $images
     * @return list<array<string, mixed>>
     */
    private function capUniqueImagePayloads(array $images, int $max): array
    {
        if ($max <= 0 || $images === []) {
            return [];
        }

        $seen = [];
        $unique = [];
        foreach ($images as $image) {
            $src = trim((string)($image['src'] ?? ''));
            $key = $src !== '' ? $src : 'alt:' . ($image['alt'] ?? '');
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $image;
            if (count($unique) >= $max) {
                break;
            }
        }

        return $unique;
    }

    /**
     * @param array<string, mixed>|null $badge
     * @return array<string, mixed>|null
     */
    private function normalizeListingBadge(?array $badge): ?array
    {
        if ($badge === null) {
            return null;
        }

        unset($badge['image']);

        return $badge;
    }

    /**
     * @return array<string, string|null>
     */
    public function getSearchTaxonomyPayload(): array
    {
        $slugResolver = new CatalogUrlSlugResolver();

        return [
            'subcategory' => $this->subcategory !== null
                ? $slugResolver->getSubcategoryPublicSlug($this->subcategory)
                : null,
            'subcategoryInternal' => $this->subcategory?->slug,
            'categorySlug' => $this->subcategory?->category !== null
                ? $slugResolver->getCategoryPublicSlug($this->subcategory->category)
                : null,
            'collectionSlug' => $this->collection?->direction !== null
                ? $slugResolver->getDirectionPublicSlug($this->collection->direction)
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toCartLineApiItem(int $quantity, ?User $dealer = null): array
    {
        $pricing = $this->buildPricingApiPayload($dealer);

        return self::applyPricingApiFields([
            'productId' => $this->slug,
            'title' => $this->title,
            'quantity' => $quantity,
            'custom' => (bool)$this->is_custom,
            'catalogModelId' => $this->model_id !== null ? (int)$this->model_id : null,
            'image' => $this->resolvePrimaryImagePayload(),
        ], $pricing);
    }

    public function toSearchApiItem(?User $dealer = null): array
    {
        $pricing = $this->buildPricingApiPayload($dealer);
        $item = $this->toListingCard($dealer);
        $item['title'] = $this->title;
        $item['subtitle'] = $this->subtitle ?: null;
        $item['description'] = $this->description ?: null;
        $item['type'] = $this->subcategory?->label;
        $item['collection'] = $this->collection?->getDisplayName();
        $item['price'] = $pricing['priceDisplay'] ?? null;

        return $this->finalizeSearchDocument($item, $dealer, $pricing);
    }

    /**
     * @param array<string, mixed> $item
     * @param array<string, mixed> $pricing
     * @return array<string, mixed>
     */
    private function finalizeSearchDocument(array $item, ?User $dealer, array $pricing): array
    {
        $slugResolver = new CatalogUrlSlugResolver();
        $productUrl = $slugResolver->buildProductUrl((string)$this->slug);
        $taxonomy = $this->getSearchTaxonomyPayload();

        $item['subcategory'] = $taxonomy['subcategory'];
        $item['categorySlug'] = $taxonomy['categorySlug'];
        $item['collectionSlug'] = $taxonomy['collectionSlug'];
        $item['href'] = $productUrl;
        $item['to'] = $productUrl;
        if (!array_key_exists('price', $item)) {
            $item['price'] = $pricing['priceDisplay'] ?? null;
        }

        if ($this->fabricColor !== null) {
            $item['fabricColorLabel'] = $this->fabricColor->getApiLabel();
        }

        $item['subcategoryLabel'] = $this->subcategory?->label;
        $item['_productId'] = (int)$this->id;
        $item['_collectionId'] = (int)($this->collection_id ?? 0);
        $item['_directionId'] = (int)($this->collection?->direction_id ?? 0);
        if ($this->collection?->direction !== null) {
            $item['_directionSlug'] = $slugResolver->getDirectionPublicSlug($this->collection->direction);
        }
        $item['_collectionSortName'] = mb_strtolower(trim((string)($this->collection?->getDisplayName() ?? '')));
        $item['_groupKey'] = $this->getListingGroupKey();
        $item['_collectionKey'] = (int)($this->collection_id ?? 0) > 0
            ? (int)$this->collection_id
            : -1 * (int)$this->id;
        $item['_groupSort'] = $this->getListingGroupSort();
        $item['_intraSort'] = $this->getListingIntraSort();
        $item['_priceAmount'] = $dealer !== null && $dealer->isDealer() && isset($item['dealerPrice'])
            ? (int)$item['dealerPrice']
            : ($item['retailPrice'] ?? $this->price_amount);
        $item['_isPopular'] = (bool)$this->is_popular;
        $item['_badgeVariant'] = $this->badge?->variant;
        $item['_createdAt'] = $this->created_at;
        $item['_sortOrder'] = (int)$this->sort_order;
        $item['_fabricSort'] = (int)($this->fabricColor?->fabricCollection?->sort_order ?? 0);
        $item['_colorSort'] = (int)($this->fabricColor?->sort_order ?? 0);

        return $item;
    }
}
