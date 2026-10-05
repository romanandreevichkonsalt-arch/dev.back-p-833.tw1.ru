<?php

namespace app\services\catalog;

use app\models\CatalogProduct;
use app\models\User;
use app\services\cache\ApiResponseCache;
use app\services\dealer\DealerPriceListService;
use yii\web\NotFoundHttpException;

final class CatalogProductDetailService
{
    public function __construct(
        private readonly ApiResponseCache $cache = new ApiResponseCache(),
        private readonly CatalogUrlSlugResolver $slugResolver = new CatalogUrlSlugResolver(),
        private readonly CatalogProductListingService $listing = new CatalogProductListingService(),
        private readonly DealerPriceListService $dealerPriceListService = new DealerPriceListService(),
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getBySlug(string $slug, ?User $dealer = null): array
    {
        $slug = trim($slug);
        if ($slug === '') {
            throw new NotFoundHttpException('Товар не найден.');
        }

        $config = \Yii::$app->params['apiCache'] ?? [];

        if ($dealer !== null && $dealer->isDealer()) {
            return $this->buildDetail($slug, $dealer);
        }

        return $this->cache->get(
            'catalog',
            'product-detail:' . $slug,
            fn (): array => $this->buildDetail($slug, null),
            (int)($config['catalogProductsTtl'] ?? 300)
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDetail(string $slug, ?User $dealer): array
    {
        $product = CatalogProduct::find()
            ->alias('p')
            ->where(['p.slug' => $slug, 'p.is_active' => true]);

        CatalogProductPublicVisibility::apply($product, 'p');

        $product = $product->with([
                'image',
                'video',
                'collection.direction',
                'subcategory.category',
                'badge.image',
                'layout',
                'catalogModel.modelPrices',
                'catalogModel.category',
                'catalogModel.subcategory',
                'catalogModel.collection',
                'catalogModel.badge.image',
                'catalogModel.layout',
                'catalogModel.video',
                'catalogModel.modelImages.media',
                'catalogModel.modelInteriorImages.media',
                'catalogModel.modelDimensionImages.media',
                'fabricColor.catalogColor.colorImages.media',
                'fabricColor.catalogColor.swatchMedia',
                'fabricColor.fabricCollection',
                'catalogModel.fabricCollections.priceCategory',
                'catalogModel.fabricCollections.activeColors.catalogColor.colorImages.media',
                'catalogModel.fabricCollections.activeColors.catalogColor.swatchMedia',
                'catalogModel.fabricCollections.activeColors.swatchMedia',
                'catalogModel.fabricCollections.activeColors.fabricCollection',
                'catalogModel.products',
            ])
            ->one();

        if ($product === null) {
            throw new NotFoundHttpException('Товар не найден.');
        }

        $detail = $product->toMenuApiItem($dealer);
        $detail['slug'] = $product->slug;
        $detail['href'] = $this->slugResolver->buildProductUrl((string)$product->slug);
        $detail['collectionSlug'] = $product->collection?->direction !== null
            ? $this->slugResolver->getDirectionPublicSlug($product->collection->direction)
            : null;
        $detail['modelLineSlug'] = $product->collection?->slug;
        $detail['categorySlug'] = $product->subcategory?->category !== null
            ? $this->slugResolver->getCategoryPublicSlug($product->subcategory->category)
            : null;
        $detail['subcategorySlug'] = $product->subcategory !== null
            ? $this->slugResolver->getSubcategoryPublicSlug($product->subcategory)
            : null;
        $detail['recommended'] = $this->buildRecommended($product, $dealer);

        if ($product->isGeneratedFromModel() && $product->catalogModel !== null) {
            $productsByFabricColorId = [];
            foreach ($product->catalogModel->products as $modelProduct) {
                if ($modelProduct->fabric_color_id === null || !$modelProduct->is_active) {
                    continue;
                }
                $productsByFabricColorId[(int)$modelProduct->fabric_color_id] = $modelProduct;
            }

            $detail['fabrics'] = (new CatalogModelFabricsBuilder())->build(
                $product->catalogModel,
                $productsByFabricColorId,
                $dealer,
            );
        } else {
            $detail['fabrics'] = [];
        }

        $orderFormBlank = $this->dealerPriceListService->getOrderFormBlankPayload();
        if ($orderFormBlank !== null) {
            $detail['orderFormBlank'] = $orderFormBlank;
        }

        return $detail;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildRecommended(CatalogProduct $product, ?User $dealer = null): array
    {
        if ($product->subcategory_id === null) {
            return [];
        }

        $listQuery = CatalogProduct::find()
            ->alias('p')
            ->where([
                'p.is_active' => true,
                'p.is_custom' => false,
                'p.subcategory_id' => $product->subcategory_id,
            ])
            ->andWhere(['<>', 'p.id', $product->id]);

        $recommended = $this->listing->takeRoundRobin($listQuery, 'default', 8);

        return array_map(
            static fn (CatalogProduct $item): array => $item->toListingCard($dealer),
            $recommended
        );
    }
}
