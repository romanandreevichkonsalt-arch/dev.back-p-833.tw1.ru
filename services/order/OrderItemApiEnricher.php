<?php

namespace app\services\order;

use app\models\CatalogProduct;
use app\models\OrderItem;
use app\services\catalog\CatalogUrlSlugResolver;

class OrderItemApiEnricher
{
    /**
     * @param array<string, CatalogProduct> $productsBySlug
     * @return array<string, mixed>
     */
    public function enrich(OrderItem $item, array $productsBySlug, ?string $orderNumber = null): array
    {
        $payload = $item->toApiItem();
        $slug = trim((string)$item->product_sku);

        if ($orderNumber !== null && $payload['attachment'] !== null && $slug !== '') {
            $payload['attachment']['downloadUrl'] = $this->buildAttachmentDownloadUrl($orderNumber, $slug);
        }

        if ($slug === '') {
            return $payload;
        }

        $product = $productsBySlug[$slug] ?? null;
        if ($product === null) {
            $payload['href'] = '/product/' . rawurlencode($slug);

            return $payload;
        }

        $slugResolver = new CatalogUrlSlugResolver();
        $payload['href'] = $slugResolver->buildProductUrl($slug);
        $payload['image'] = $product->resolvePrimaryImagePayload();
        $payload['specLine1'] = $product->fabricColor?->getApiLabel();
        $payload['specLine2'] = $product->collection?->getDisplayName() ?: $product->subcategory?->label;

        return $payload;
    }

    /**
     * @param list<OrderItem> $items
     * @return array<string, CatalogProduct>
     */
    public function loadProductsForItems(array $items): array
    {
        $slugs = [];
        foreach ($items as $item) {
            $slug = trim((string)$item->product_sku);
            if ($slug !== '') {
                $slugs[] = $slug;
            }
        }

        if ($slugs === []) {
            return [];
        }

        return CatalogProduct::find()
            ->where(['slug' => array_values(array_unique($slugs))])
            ->with([
                'image',
                'collection.image',
                'subcategory',
                'fabricColor',
                'catalogModel.modelImages.media',
            ])
            ->indexBy('slug')
            ->all();
    }

    /**
     * @param list<OrderItem> $items
     * @param array<string, CatalogProduct> $productsBySlug
     * @return array{images: list<string>, extraCount: int}
     */
    public function buildSummaryImages(array $items, array $productsBySlug, int $limit = 3): array
    {
        $images = [];
        foreach ($items as $item) {
            if (count($images) >= $limit) {
                break;
            }

            $slug = trim((string)$item->product_sku);
            if ($slug === '' || !isset($productsBySlug[$slug])) {
                continue;
            }

            $miniUrl = $this->resolveMiniImageUrl($productsBySlug[$slug]);
            if ($miniUrl === null) {
                continue;
            }

            $images[] = $miniUrl;
        }

        $totalItems = count($items);

        return [
            'images' => $images,
            'extraCount' => $totalItems > $limit ? $totalItems - $limit : 0,
        ];
    }

    private function resolveMiniImageUrl(CatalogProduct $product): ?string
    {
        $payload = $product->resolvePrimaryImagePayload();
        $srcSet = $payload['srcSet'] ?? null;
        if (is_array($srcSet)) {
            $mini = trim((string)($srcSet['mini'] ?? ''));
            if ($mini !== '') {
                return $mini;
            }
        }

        $src = trim((string)($payload['src'] ?? ''));

        return $src !== '' ? $src : null;
    }

    public function buildAttachmentDownloadUrl(string $orderNumber, string $productId): string
    {
        return '/api/v1/orders/' . rawurlencode($orderNumber) . '/items/' . rawurlencode($productId) . '/attachment';
    }
}
