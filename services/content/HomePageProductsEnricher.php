<?php

namespace app\services\content;

use app\models\CatalogProduct;
use app\modules\admin\helpers\HomePageProductsHelper;
use app\services\catalog\CatalogUrlSlugResolver;

final class HomePageProductsEnricher
{
    private const TARGET_COUNT = 3;

    private const LAYOUTS = ['featured', 'stacked', 'stacked'];

    /**
     * @param array<int|string, mixed> $products
     * @return list<array<string, mixed>>
     */
    public function enrich(array $products): array
    {
        $normalized = [];
        foreach ($products as $product) {
            if (!is_array($product) || !$this->hasValidImage($product)) {
                continue;
            }
            $normalized[] = $this->normalizeProductLink($product);
        }

        if (count($normalized) >= self::TARGET_COUNT) {
            return array_slice($normalized, 0, self::TARGET_COUNT);
        }

        $usedHrefs = [];
        foreach ($normalized as $item) {
            $href = trim((string)($item['to'] ?? ''));
            if ($href !== '') {
                $usedHrefs[$href] = true;
            }
        }

        $candidates = CatalogProduct::find()
            ->where(['is_active' => true, 'is_custom' => false])
            ->with(['image', 'collection', 'catalogModel.collection', 'fabricColor'])
            ->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC])
            ->limit(24)
            ->all();

        foreach ($candidates as $product) {
            if (count($normalized) >= self::TARGET_COUNT) {
                break;
            }

            $href = (new CatalogUrlSlugResolver())->buildProductUrl((string)$product->slug);
            if (isset($usedHrefs[$href])) {
                continue;
            }

            $image = $product->resolvePrimaryImagePayload();
            if (!$this->imagePayloadIsValid($image)) {
                continue;
            }

            $normalized[] = $this->buildItemFromProduct($product, $image, count($normalized));
            $usedHrefs[$href] = true;
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function hasValidImage(array $item): bool
    {
        $image = $item['image'] ?? null;

        return is_array($image) && $this->imagePayloadIsValid($image);
    }

    /**
     * @param array<string, mixed> $image
     */
    private function imagePayloadIsValid(array $image): bool
    {
        $src = trim((string)($image['src'] ?? ''));
        if ($src !== '') {
            return true;
        }

        $srcSet = $image['srcSet'] ?? null;
        if (!is_array($srcSet)) {
            return false;
        }

        foreach (['medium', 'mini', 'original'] as $variant) {
            if (trim((string)($srcSet[$variant] ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $image
     * @return array<string, mixed>
     */
    private function buildItemFromProduct(CatalogProduct $product, array $image, int $index): array
    {
        $model = $product->catalogModel;
        $name = $model?->title ?? $product->title;

        return [
            'number' => sprintf('%02d', $index + 1),
            'name' => $name,
            'collection' => $product->collection?->getDisplayName() ?? $model?->collection?->getDisplayName() ?? '',
            'fabric' => $product->fabricColor?->getApiLabel() ?? '',
            'price' => trim((string)$product->price_display),
            'swatchCount' => '',
            'layout' => self::LAYOUTS[$index] ?? 'stacked',
            'imagePosition' => $product->image_position ?? 'center',
            'to' => HomePageProductsHelper::productCardUrl($product),
            'image' => $image,
        ];
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function normalizeProductLink(array $item): array
    {
        $productId = HomePageProductsHelper::resolveProductIdFromApiItem($item);
        if ($productId === null) {
            return $item;
        }

        $product = CatalogProduct::findOne($productId);
        if ($product === null) {
            return $item;
        }

        $item['to'] = HomePageProductsHelper::productCardUrl($product);

        return $item;
    }
}
