<?php

namespace app\services\catalog;

use app\models\CatalogModel;
use app\models\CatalogProduct;
use app\models\User;
use app\services\catalog\CatalogListingValueParser;
use app\services\dealer\DealerPricingService;

final class CatalogModelFabricsBuilder
{
    public function __construct(
        private readonly CatalogUrlSlugResolver $slugResolver = new CatalogUrlSlugResolver(),
    ) {
    }

    /**
     * Фактура → коллекция → цвета, подключённые к модели.
     *
     * @param array<int, CatalogProduct> $productsByFabricColorId
     *
     * @return list<array<string, mixed>>
     */
    public function build(CatalogModel $model, array $productsByFabricColorId = [], ?User $dealer = null): array
    {
        $pricingService = new DealerPricingService();
        $dealerDiscountPercent = $dealer !== null && $dealer->isDealer()
            ? $pricingService->getEffectiveDiscountPercent($dealer)
            : null;
        $fabricCollections = $model->fabricCollections;
        usort(
            $fabricCollections,
            static fn ($left, $right): int => [$left->sort_order, $left->name] <=> [$right->sort_order, $right->name]
        );

        /** @var array<string, array{texture: string, collections: list<array<string, mixed>>}> $groups */
        $groups = [];
        $groupOrder = [];

        foreach ($fabricCollections as $fabricCollection) {
            if (!$fabricCollection->is_active) {
                continue;
            }

            $texture = trim((string)$fabricCollection->texture);
            if ($texture === '') {
                $texture = trim((string)$fabricCollection->material_kind) ?: '—';
            }

            if (!isset($groups[$texture])) {
                $groups[$texture] = [
                    'texture' => $texture,
                    'collections' => [],
                ];
                $groupOrder[] = $texture;
            }

            $colors = [];
            foreach ($fabricCollection->activeColors as $color) {
                $colorPayload = $color->toApiPayload();
                unset($colorPayload['meterPrice']);
                $product = $productsByFabricColorId[(int)$color->id] ?? null;
                if ($product !== null) {
                    $colorPayload['productSlug'] = $product->slug;
                    $colorPayload['href'] = $this->slugResolver->buildProductUrl((string)$product->slug);
                }
                $colors[] = $colorPayload;
            }

            if ($colors === []) {
                continue;
            }

            $priceCategoryId = $fabricCollection->getPriceCategoryForProducts();
            $modelPrice = $priceCategoryId !== null
                ? $model->getPriceForPriceCategoryId($priceCategoryId)
                : null;

            $collectionPayload = [
                'id' => $fabricCollection->slug,
                'name' => $fabricCollection->name,
                'category' => $fabricCollection->getPriceCategoryNumberForApi(),
                'price' => $modelPrice,
                'colors' => $colors,
            ];
            $retailPrice = CatalogListingValueParser::parsePriceAmount($modelPrice);
            if ($retailPrice !== null && $retailPrice > 0) {
                $collectionPayload['retailPrice'] = $retailPrice;
                if ($dealer !== null && $dealer->isDealer()) {
                    $sampleProduct = null;
                    foreach ($fabricCollection->activeColors as $color) {
                        $sampleProduct = $productsByFabricColorId[(int)$color->id] ?? null;
                        if ($sampleProduct !== null) {
                            break;
                        }
                    }
                    if ($sampleProduct !== null) {
                        $prices = $pricingService->buildProductPrices($sampleProduct, $dealer);
                        if (isset($prices['dealerPrice'])) {
                            $collectionPayload['dealerPrice'] = $prices['dealerPrice'];
                        }
                    } elseif ($dealerDiscountPercent !== null) {
                        $dealerUnit = $pricingService->applyDiscount($retailPrice, $dealerDiscountPercent);
                        if ($dealerUnit !== null) {
                            $collectionPayload['dealerPrice'] = $dealerUnit;
                        }
                    }
                }
            }

            $groups[$texture]['collections'][] = $collectionPayload;
        }

        $result = [];
        foreach ($groupOrder as $texture) {
            if ($groups[$texture]['collections'] === []) {
                continue;
            }
            $result[] = $groups[$texture];
        }

        return $result;
    }
}
