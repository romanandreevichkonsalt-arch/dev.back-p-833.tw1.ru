<?php

namespace app\services\search;

use app\models\CatalogModelImage;
use app\models\CatalogProduct;
use app\models\MediaFile;
use app\services\catalog\CatalogProductPublicVisibility;
use app\services\catalog\CatalogUrlSlugResolver;
use Yii;
use yii\db\Query;

/**
 * Fast searchable index build: asArray hydrate, no TEXT columns, no modelPrices/DealerPricing.
 */
final class SearchLiteIndexBuilder
{
    /** @var array<int, array<string, mixed>> model_id => media row (first angle) */
    private array $modelPrimaryImages = [];

    public function __construct(
        private readonly SearchDocumentBuilder $documentBuilder = new SearchDocumentBuilder(),
        private readonly CatalogUrlSlugResolver $slugResolver = new CatalogUrlSlugResolver(),
        private readonly SearchQueryResolver $queryResolver = new SearchQueryResolver(),
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function build(): array
    {
        @ini_set('memory_limit', '512M');

        $this->modelPrimaryImages = $this->loadModelPrimaryImages();

        $query = CatalogProduct::find()
            ->alias('p')
            ->select([
                'p.id',
                'p.slug',
                'p.title',
                'p.subtitle',
                'p.price_amount',
                'p.price_display',
                'p.subcategory_id',
                'p.collection_id',
                'p.model_id',
                'p.fabric_color_id',
                'p.image_id',
                'p.badge_id',
                'p.is_popular',
                'p.is_active',
                'p.is_custom',
                'p.sort_order',
                'p.created_at',
            ])
            ->where(['p.is_active' => true, 'p.is_custom' => false]);

        CatalogProductPublicVisibility::apply($query, 'p');

        $query
            ->asArray()
            ->with([
                'image' => static function ($q): void {
                    $q->select([
                        'id',
                        'path',
                        'path_medium',
                        'path_mini',
                        'path_large',
                        'path_listing_medium',
                        'path_listing_mini',
                        'listing_frame_locked',
                        'alt',
                        'filename',
                        'kind',
                    ]);
                },
                'collection' => static function ($q): void {
                    $q->select(['id', 'name', 'title', 'direction_id', 'sort_order', 'slug', 'image_id']);
                },
                'collection.direction' => static function ($q): void {
                    $q->select(['id', 'slug']);
                },
                'collection.image' => static function ($q): void {
                    $q->select([
                        'id',
                        'path',
                        'path_medium',
                        'path_mini',
                        'path_large',
                        'path_listing_medium',
                        'path_listing_mini',
                        'listing_frame_locked',
                        'alt',
                        'filename',
                        'kind',
                    ]);
                },
                'subcategory' => static function ($q): void {
                    $q->select(['id', 'label', 'slug', 'url_slug', 'category_id']);
                },
                'subcategory.category' => static function ($q): void {
                    $q->select(['id', 'slug', 'url_slug']);
                },
                'badge' => static function ($q): void {
                    $q->select(['id', 'label', 'variant']);
                },
                'catalogModel' => static function ($q): void {
                    $q->select(['id', 'sort_order']);
                },
                'fabricColor' => static function ($q): void {
                    $q->select([
                        'id',
                        'api_label',
                        'design_code',
                        'sort_order',
                        'fabric_collection_id',
                    ]);
                },
                'fabricColor.fabricCollection' => static function ($q): void {
                    $q->select(['id', 'name', 'sort_order']);
                },
            ])
            ->orderBy(['p.sort_order' => SORT_ASC, 'p.id' => SORT_ASC]);

        $documents = [];
        /** @var list<array<string, mixed>> $batch */
        foreach ($query->batch(500) as $batch) {
            foreach ($batch as $row) {
                $documents[] = $this->enrich($this->toDocument($row));
            }
        }

        return $documents;
    }

    /**
     * Same fallback chain as CatalogProduct::resolvePrimaryImagePayload(forListing: true),
     * but one SQL for all model first-angles (SKU almost never have product.image_id).
     *
     * @return array<int, array<string, mixed>>
     */
    private function loadModelPrimaryImages(): array
    {
        $rows = (new Query())
            ->select([
                'mi.model_id',
                'm.path',
                'm.path_medium',
                'm.path_mini',
                'm.path_large',
                'm.path_listing_medium',
                'm.path_listing_mini',
                'm.listing_frame_locked',
                'm.alt',
                'm.filename',
            ])
            ->from(['mi' => CatalogModelImage::tableName()])
            ->innerJoin(['m' => MediaFile::tableName()], '[[m.id]] = [[mi.media_file_id]]')
            ->where(['mi.purpose' => CatalogModelImage::PURPOSE_ANGLE])
            ->orderBy([
                'mi.model_id' => SORT_ASC,
                'mi.sort_order' => SORT_ASC,
                'mi.id' => SORT_ASC,
            ])
            ->all(Yii::$app->db);

        $map = [];
        foreach ($rows as $row) {
            $modelId = (int)$row['model_id'];
            if (isset($map[$modelId])) {
                continue;
            }
            $map[$modelId] = $row;
        }

        return $map;
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function enrich(array $item): array
    {
        $searchText = $this->documentBuilder->buildSearchText($item);
        $item['_searchHaystackNormalized'] = $this->queryResolver->normalize($searchText);
        $item['_titleNormalized'] = $this->queryResolver->normalize((string)($item['title'] ?? ''));

        return $this->documentBuilder->compactIndexDocument($item);
    }

    /**
     * @param array<string, mixed> $product
     * @return array<string, mixed>
     */
    private function toDocument(array $product): array
    {
        $id = (int)$product['id'];
        $modelId = (int)($product['model_id'] ?? 0);
        $collectionId = (int)($product['collection_id'] ?? 0);
        $sortOrder = (int)($product['sort_order'] ?? 0);
        $priceAmount = $product['price_amount'] !== null && (int)$product['price_amount'] > 0
            ? (int)$product['price_amount']
            : null;

        $collection = is_array($product['collection'] ?? null) ? $product['collection'] : null;
        $direction = is_array($collection['direction'] ?? null) ? $collection['direction'] : null;
        $subcategory = is_array($product['subcategory'] ?? null) ? $product['subcategory'] : null;
        $category = is_array($subcategory['category'] ?? null) ? $subcategory['category'] : null;
        $badgeRow = is_array($product['badge'] ?? null) ? $product['badge'] : null;
        $catalogModel = is_array($product['catalogModel'] ?? null) ? $product['catalogModel'] : null;
        $fabricColor = is_array($product['fabricColor'] ?? null) ? $product['fabricColor'] : null;
        $fabricCollection = is_array($fabricColor['fabricCollection'] ?? null) ? $fabricColor['fabricCollection'] : null;

        $collectionName = '';
        if ($collection !== null) {
            $collectionName = trim((string)($collection['name'] ?? '')) !== ''
                ? (string)$collection['name']
                : (string)($collection['title'] ?? '');
        }

        $productSlug = (string)$product['slug'];
        $productUrl = $this->slugResolver->buildProductUrl($productSlug);
        $subcategoryLabel = $subcategory !== null ? (string)($subcategory['label'] ?? '') : null;
        $fabricColorLabel = $this->fabricColorApiLabel($fabricColor, $fabricCollection);

        $image = $this->resolvePrimaryListingImage(
            is_array($product['image'] ?? null) ? $product['image'] : null,
            $modelId,
            is_array($collection['image'] ?? null) ? $collection['image'] : null,
            (string)$product['title'],
            $collectionName !== '' ? $collectionName : (string)$product['title'],
        );

        $badge = null;
        if ($badgeRow !== null) {
            $badge = [
                'text' => $badgeRow['label'] ?? null,
                'variant' => $badgeRow['variant'] ?? null,
            ];
        }

        $directionId = (int)($collection['direction_id'] ?? 0);
        $modelSort = (int)($catalogModel['sort_order'] ?? $sortOrder);
        $fabricSort = (int)($fabricCollection['sort_order'] ?? 0);
        $colorSort = (int)($fabricColor['sort_order'] ?? 0);

        $item = [
            'slug' => $productSlug,
            'id' => $productSlug,
            'title' => $product['title'],
            'type' => $subcategoryLabel,
            'collection' => $collectionName !== '' ? $collectionName : null,
            'image' => $image,
            'badge' => $badge,
            'retailPrice' => $priceAmount,
            'subcategory' => $subcategory !== null ? $this->publicSlug($subcategory) : null,
            'categorySlug' => $category !== null ? $this->publicSlug($category) : null,
            'collectionSlug' => $direction !== null ? (string)($direction['slug'] ?? '') : null,
            'href' => $productUrl,
            'to' => $productUrl,
            'subcategoryLabel' => $subcategoryLabel,
            'fabricColorLabel' => $fabricColorLabel,
            '_productId' => $id,
            '_collectionId' => $collectionId,
            '_directionId' => $directionId,
            '_collectionSortName' => mb_strtolower(trim($collectionName)),
            '_groupKey' => $modelId > 0 ? $modelId : -1 * $id,
            '_collectionKey' => $collectionId > 0 ? $collectionId : -1 * $id,
            '_groupSort' => sprintf(
                '%010d-%010d-%010d',
                (int)($collection['sort_order'] ?? 0),
                $modelSort,
                $modelId
            ),
            '_intraSort' => sprintf('%010d-%010d-%010d', $fabricSort, $colorSort, $id),
            '_priceAmount' => $priceAmount,
            '_isPopular' => (bool)$product['is_popular'],
            '_badgeVariant' => $badgeRow['variant'] ?? null,
            '_createdAt' => $product['created_at'],
            '_sortOrder' => $sortOrder,
            '_fabricSort' => $fabricSort,
            '_colorSort' => $colorSort,
        ];

        if ($direction !== null && ($direction['slug'] ?? '') !== '') {
            $item['_directionSlug'] = (string)$direction['slug'];
        }

        if ($badge === null) {
            unset($item['badge']);
        }

        if ($item['collectionSlug'] === '') {
            $item['collectionSlug'] = null;
        }

        return $item;
    }

    /**
     * @param array<string, mixed>|null $productImage
     * @param array<string, mixed>|null $collectionImage
     * @return array{src: string|null, alt: string}
     */
    private function resolvePrimaryListingImage(
        ?array $productImage,
        int $modelId,
        ?array $collectionImage,
        string $productTitle,
        string $collectionAlt,
    ): array {
        $image = $this->listingImagePayload($productImage, $productTitle);
        if ($this->hasImageSrc($image)) {
            return $image;
        }

        if ($modelId > 0 && isset($this->modelPrimaryImages[$modelId])) {
            $image = $this->listingImagePayload($this->modelPrimaryImages[$modelId], $productTitle);
            if ($this->hasImageSrc($image)) {
                return $image;
            }
        }

        $image = $this->listingImagePayload($collectionImage, $collectionAlt);
        if ($this->hasImageSrc($image)) {
            return $image;
        }

        return MediaFile::emptyImagePayload($productTitle);
    }

    /**
     * @param array{src: string|null, alt: string} $image
     */
    private function hasImageSrc(array $image): bool
    {
        return trim((string)($image['src'] ?? '')) !== '';
    }

    /**
     * @param array<string, mixed> $image
     */
    private function resolveListingMediumPath(array $image): string
    {
        if (!empty($image['listing_frame_locked'])) {
            $listing = trim((string)($image['path_listing_medium'] ?? ''));
            if ($listing !== '') {
                return $listing;
            }
        }

        return trim((string)($image['path_medium'] ?? ''));
    }

    /**
     * @param array<string, mixed>|null $image
     * @return array{src: string|null, alt: string}
     */
    private function listingImagePayload(?array $image, string $title): array
    {
        if ($image === null) {
            return MediaFile::emptyImagePayload($title);
        }

        $path = $this->resolveListingMediumPath($image);
        if ($path === '') {
            $path = trim((string)($image['path_mini'] ?? ''));
        }
        if ($path === '') {
            $path = trim((string)($image['path'] ?? ''));
        }
        if ($path === '') {
            return MediaFile::emptyImagePayload($title);
        }

        $alt = trim((string)($image['alt'] ?? ''));
        if ($alt === '') {
            $alt = trim((string)($image['filename'] ?? '')) !== ''
                ? (string)$image['filename']
                : $title;
        }

        return [
            'src' => '/' . ltrim($path, '/'),
            'alt' => $alt,
        ];
    }

    /**
     * @param array<string, mixed>|null $fabricColor
     * @param array<string, mixed>|null $fabricCollection
     */
    private function fabricColorApiLabel(?array $fabricColor, ?array $fabricCollection): ?string
    {
        if ($fabricColor === null) {
            return null;
        }

        $stored = trim((string)($fabricColor['api_label'] ?? ''));
        if ($stored !== '') {
            return $stored;
        }

        $collectionName = trim((string)($fabricCollection['name'] ?? ''));
        $designCode = trim((string)($fabricColor['design_code'] ?? ''));
        if ($collectionName === '' && $designCode === '') {
            return null;
        }

        return trim($collectionName . ($designCode !== '' ? ' ' . $designCode : ''));
    }

    /**
     * @param array<string, mixed> $row
     */
    private function publicSlug(array $row): string
    {
        $urlSlug = trim((string)($row['url_slug'] ?? ''));

        return $urlSlug !== '' ? $urlSlug : (string)($row['slug'] ?? '');
    }
}
