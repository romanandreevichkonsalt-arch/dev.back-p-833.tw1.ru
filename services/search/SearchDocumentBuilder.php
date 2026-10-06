<?php

namespace app\services\search;

use app\models\MediaFile;
use app\services\dealer\DealerPricingService;

class SearchDocumentBuilder
{
    public const PREVIEW_PRODUCTS_PER_CATEGORY = 3;

    /**
     * @param array<string, mixed> $item
     *
     * @return array<string, mixed>
     */
    public function enrichProduct(array $item): array
    {
        $resolver = new SearchQueryResolver();
        $item['_searchText'] = $this->buildSearchText($item);
        $item['_searchHaystackNormalized'] = $resolver->normalize($item['_searchText']);
        $item['_titleNormalized'] = $resolver->normalize((string)($item['title'] ?? $item['name'] ?? ''));

        return $this->compactIndexDocument($item);
    }

    /**
     * Уменьшает документ перед кэшем searchable-products (~15k SKU).
     *
     * @param array<string, mixed> $document
     * @return array<string, mixed>
     */
    public function compactIndexDocument(array $document): array
    {
        unset(
            $document['description'],
            $document['subtitle'],
            $document['priceDisplay'],
            $document['_searchText'],
            $document['images'],
            $document['model'],
            $document['swatches'],
            $document['swatchCount'],
            $document['materials'],
            $document['dimensions'],
        );

        if (isset($document['image']) && is_array($document['image'])) {
            $document['image'] = $this->compactImagePayload($document['image']);
        }

        if (isset($document['badge']) && is_array($document['badge'])) {
            unset($document['badge']['image']);
        }

        // Keep haystack short: match needs tokens, not full product essays.
        if (isset($document['_searchHaystackNormalized']) && is_string($document['_searchHaystackNormalized'])) {
            $haystack = $document['_searchHaystackNormalized'];
            if (mb_strlen($haystack) > 240) {
                $document['_searchHaystackNormalized'] = mb_substr($haystack, 0, 240);
            }
        }

        return $document;
    }

    /**
     * @param array<string, mixed> $image
     * @return array{src: string, alt: string}
     */
    private function compactImagePayload(array $image): array
    {
        return [
            'src' => trim((string)($image['src'] ?? '')),
            'alt' => trim((string)($image['alt'] ?? '')),
        ];
    }

    /**
     * @param array<string, mixed> $item
     */
    public function buildSearchText(array $item): string
    {
        // Lite index: skip long description / specs — they inflate FileCache and slow str_contains.
        $parts = [
            $item['title'] ?? '',
            $item['subtitle'] ?? '',
            $item['type'] ?? '',
            $item['subcategoryLabel'] ?? '',
            $item['collection'] ?? '',
            $item['fabricColorLabel'] ?? '',
            $item['id'] ?? '',
        ];

        if (isset($item['fabricColor']) && is_array($item['fabricColor'])) {
            foreach (['label', 'collection'] as $field) {
                if (!empty($item['fabricColor'][$field]) && is_string($item['fabricColor'][$field])) {
                    $parts[] = $item['fabricColor'][$field];
                }
            }
        }

        return implode(' ', array_filter($parts, static fn ($part): bool => trim((string)$part) !== ''));
    }

    /**
     * Публичная карточка autocomplete / листинга поиска (без лишних полей листинга каталога).
     *
     * @param array<string, mixed> $item
     *
     * @return array<string, mixed>
     */
    public function toPublicProduct(array $item): array
    {
        $slug = trim((string)($item['slug'] ?? $item['id'] ?? ''));
        $title = trim((string)($item['title'] ?? $item['name'] ?? ''));

        $retailPrice = isset($item['retailPrice']) ? (int)$item['retailPrice'] : null;
        $priceDisplay = $this->nullableString($item['priceDisplay'] ?? null);
        if ($priceDisplay === null && $retailPrice !== null) {
            $priceDisplay = (new DealerPricingService())->formatPriceDisplay($retailPrice);
        }

        $payload = [
            'id' => $slug,
            'slug' => $slug,
            'title' => $title,
            'subcategory' => $this->nullableString($item['subcategoryLabel'] ?? $item['type'] ?? null),
            'collection' => $this->nullableString($item['collection'] ?? null),
            'image' => $this->resolveSearchImage($item, $title),
            'retailPrice' => $retailPrice,
            'priceDisplay' => $priceDisplay,
            'dealerPrice' => isset($item['dealerPrice']) ? (int)$item['dealerPrice'] : null,
            'dealerDiscountPercent' => isset($item['dealerDiscountPercent']) ? (int)$item['dealerDiscountPercent'] : null,
            'badge' => is_array($item['badge'] ?? null) ? $item['badge'] : null,
            'href' => $this->nullableString($item['href'] ?? $item['to'] ?? null),
        ];

        if ($payload['badge'] === null) {
            unset($payload['badge']);
        }

        return $payload;
    }

    /**
     * До трёх SKU для превью категории: сначала популярные (если есть), затем остальные в порядке выдачи.
     *
     * @param list<array<string, mixed>> $products
     * @return list<array<string, mixed>>
     */
    public function pickCategoryPreviewDocuments(array $products, int $limit = self::PREVIEW_PRODUCTS_PER_CATEGORY): array
    {
        if ($products === [] || $limit <= 0) {
            return [];
        }

        $popular = [];
        $rest = [];
        foreach ($products as $product) {
            if (!empty($product['_isPopular'])) {
                $popular[] = $product;
            } else {
                $rest[] = $product;
            }
        }

        return array_slice(array_merge($popular, $rest), 0, $limit);
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function resolveSearchImage(array $item, string $title): array
    {
        $productImage = $item['image'] ?? null;
        if (is_array($productImage) && $this->imageHasSrc($productImage)) {
            return $productImage;
        }

        $images = $item['images'] ?? [];
        if (is_array($images)) {
            foreach ($images as $image) {
                if (is_array($image) && $this->imageHasSrc($image)) {
                    return $image;
                }
            }
        }

        $model = $item['model'] ?? null;
        if (is_array($model)) {
            $modelImages = $model['images'] ?? $model['modelImages'] ?? null;
            if (is_array($modelImages)) {
                foreach ($modelImages as $image) {
                    if (is_array($image) && $this->imageHasSrc($image)) {
                        return $image;
                    }
                }
            }
        }

        return MediaFile::emptyImagePayload($title !== '' ? $title : 'Товар');
    }

    /**
     * @param array<string, mixed> $image
     */
    private function imageHasSrc(array $image): bool
    {
        $src = $image['src'] ?? null;

        return is_string($src) && trim($src) !== '';
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string)$value);

        return $string === '' ? null : $string;
    }
}
