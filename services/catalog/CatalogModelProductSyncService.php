<?php

namespace app\services\catalog;

use app\models\CatalogFabricColor;
use app\models\CatalogModel;
use app\models\CatalogPriceCategory;
use app\models\CatalogProduct;
use Yii;

class CatalogModelProductSyncService
{
    /**
     * @param int[] $fabricColorIds
     */
    public function countProductsForFabricColorIds(array $fabricColorIds): int
    {
        $fabricColorIds = array_values(array_filter(array_map('intval', $fabricColorIds), static fn (int $id): bool => $id > 0));
        if ($fabricColorIds === []) {
            return 0;
        }

        return (int)CatalogProduct::find()
            ->where(['fabric_color_id' => $fabricColorIds])
            ->count();
    }

    public function countProductsForFabricCollectionId(int $fabricCollectionId): int
    {
        if ($fabricCollectionId <= 0) {
            return 0;
        }

        $colorIds = CatalogFabricColor::find()
            ->select('id')
            ->where(['fabric_collection_id' => $fabricCollectionId])
            ->column();

        return $this->countProductsForFabricColorIds($colorIds);
    }

    /**
     * @param int[] $fabricColorIds
     */
    public function deleteProductsForFabricColorIds(array $fabricColorIds): int
    {
        $fabricColorIds = array_values(array_filter(array_map('intval', $fabricColorIds), static fn (int $id): bool => $id > 0));
        if ($fabricColorIds === []) {
            return 0;
        }

        $deleted = 0;
        foreach (
            CatalogProduct::find()
                ->where(['fabric_color_id' => $fabricColorIds])
                ->all() as $product
        ) {
            $product->delete();
            $deleted++;
        }

        return $deleted;
    }

    public function syncForModel(CatalogModel $model): void
    {
        if ($model->isNewRecord) {
            return;
        }

        $model = CatalogModel::find()
            ->where(['id' => $model->id])
            ->with([
                'modelPrices.priceCategory',
                'collection',
                'category',
                'subcategory',
                'fabricCollections.activeColors.catalogColor.swatchMedia',
                'fabricCollections.activeColors.fabricCollection',
            ])
            ->one();

        if ($model === null) {
            return;
        }

        $this->syncDefaultProduct($model);

        $expectedColorIds = [];
        foreach ($model->fabricCollections as $fabricCollection) {
            if (!$fabricCollection->is_active) {
                continue;
            }
            foreach ($fabricCollection->activeColors as $color) {
                $expectedColorIds[(int)$color->id] = $color;
            }
        }

        $existingProducts = CatalogProduct::find()
            ->where(['model_id' => $model->id])
            ->andWhere(['not', ['fabric_color_id' => null]])
            ->indexBy('fabric_color_id')
            ->all();

        foreach ($expectedColorIds as $colorId => $color) {
            $product = $existingProducts[$colorId] ?? null;
            if ($product === null) {
                $product = new CatalogProduct();
            }
            $this->applyProductFromModelAndColor($product, $model, $color);
            $product->save(false);
            unset($existingProducts[$colorId]);
        }

        foreach ($existingProducts as $product) {
            $product->delete();
        }

        $this->deleteGhostFabricProductsForModel((int)$model->id);
    }

    /**
     * SKU с is_custom=false и fabric_color_id=null — «обнулившиеся» после удаления цвета ткани (FK SET NULL).
     */
    private function deleteGhostFabricProductsForModel(int $modelId): void
    {
        CatalogProduct::deleteAll([
            'model_id' => $modelId,
            'is_custom' => false,
            'fabric_color_id' => null,
        ]);
    }

    public function syncForFabricColor(CatalogFabricColor $color): void
    {
        if ($color->isNewRecord) {
            return;
        }

        $modelIds = Yii::$app->db->createCommand(
            'SELECT model_id FROM {{%catalog_model_fabric_collections}} WHERE fabric_collection_id = :fc',
            ['fc' => $color->fabric_collection_id]
        )->queryColumn();

        foreach ($modelIds as $modelId) {
            $model = CatalogModel::findOne((int)$modelId);
            if ($model !== null) {
                $this->syncForModel($model);
            }
        }
    }

    public function syncForFabricCollectionId(int $fabricCollectionId): void
    {
        $modelIds = Yii::$app->db->createCommand(
            'SELECT model_id FROM {{%catalog_model_fabric_collections}} WHERE fabric_collection_id = :fc',
            ['fc' => $fabricCollectionId]
        )->queryColumn();

        foreach ($modelIds as $modelId) {
            $model = CatalogModel::findOne((int)$modelId);
            if ($model !== null) {
                $this->syncForModel($model);
            }
        }
    }

    private function syncDefaultProduct(CatalogModel $model): void
    {
        $product = CatalogProduct::find()
            ->where(['model_id' => $model->id, 'fabric_color_id' => null])
            ->one();

        if ($product === null) {
            $product = new CatalogProduct();
        }

        $this->applyDefaultProduct($product, $model);
        $product->save(false);
    }

    private function applyDefaultProduct(CatalogProduct $product, CatalogModel $model): void
    {
        $title = ProductTitleBuilder::buildDefault($model);
        $slug = $this->buildProductSlugFromTitle($title, $product->isNewRecord ? null : (int)$product->id);
        $href = $this->buildProductHref($model, $slug);

        $isNew = $product->isNewRecord;
        $preservedQuantity = $isNew ? null : (int)$product->quantity;
        $preservedImageId = $isNew ? null : $product->image_id;

        $product->model_id = (int)$model->id;
        $product->fabric_color_id = null;
        $product->collection_id = (int)$model->collection_id;
        $product->subcategory_id = $model->subcategory_id;
        $product->badge_id = $model->badge_id;
        $product->layout_id = $model->layout_id;
        $product->slug = $slug;
        $product->title = $title;
        $product->subtitle = $model->subtitle;
        $product->description = $model->description;
        $product->href = $href;
        $product->price_display = $this->resolveDefaultPriceDisplay($model);
        $product->sort_order = (int)$model->sort_order;
        $this->applyListingFieldsFromModel($product, $model);

        $product->is_custom = true;
        $product->is_active = (bool)$model->is_active;

        if ($isNew) {
            $product->quantity = 0;
        } else {
            $product->quantity = $preservedQuantity ?? 0;
            $product->image_id = $preservedImageId;
        }
    }

    private function resolveDefaultPriceDisplay(CatalogModel $model): ?string
    {
        $firstCategory = CatalogPriceCategory::find()
            ->where(['is_active' => true, 'number' => 1])
            ->one();

        if ($firstCategory === null) {
            return CatalogPriceCategory::getDefaultId() !== null
                ? $model->getPriceForPriceCategoryId((int)CatalogPriceCategory::getDefaultId())
                : null;
        }

        return $model->getPriceForPriceCategoryId((int)$firstCategory->id);
    }

    private function buildProductSlugFromTitle(string $title, ?int $excludeProductId = null): string
    {
        $slug = ProductTitleBuilder::buildSlugFromTitle($title);
        if ($slug === '') {
            $slug = 'product';
        }

        $base = $slug;
        $suffix = 2;
        while ($this->isSlugTaken($slug, $excludeProductId)) {
            $slug = $base . '-' . $suffix;
            $suffix++;
        }

        return $slug;
    }

    private function applyProductFromModelAndColor(
        CatalogProduct $product,
        CatalogModel $model,
        CatalogFabricColor $color
    ): void {
        $title = ProductTitleBuilder::build($model, $color);
        $slug = $this->buildProductSlugFromTitle($title, $product->isNewRecord ? null : (int)$product->id);
        $href = $this->buildProductHref($model, $slug);

        $isNew = $product->isNewRecord;
        $preservedQuantity = $isNew ? null : (int)$product->quantity;
        $preservedImageId = $isNew ? null : $product->image_id;

        $product->model_id = (int)$model->id;
        $product->fabric_color_id = (int)$color->id;
        $product->collection_id = (int)$model->collection_id;
        $product->subcategory_id = $model->subcategory_id;
        $product->badge_id = $model->badge_id;
        $product->layout_id = $model->layout_id;
        $product->slug = $slug;
        $product->title = $title;
        $product->subtitle = $model->subtitle;
        $product->description = $model->description;
        $product->href = $href;
        $fabricCollection = $color->fabricCollection;
        $priceCategoryId = $fabricCollection !== null
            ? $fabricCollection->getPriceCategoryForProducts()
            : CatalogPriceCategory::getDefaultId();

        $product->price_display = $priceCategoryId !== null
            ? $model->getPriceForPriceCategoryId($priceCategoryId)
            : null;
        $product->sort_order = (int)$model->sort_order;
        $this->applyListingFieldsFromModel($product, $model);
        $product->is_custom = false;
        $product->is_active = $this->resolveFabricProductIsActive($model, $color);

        if ($isNew) {
            $product->quantity = 0;
        } else {
            $product->quantity = $preservedQuantity ?? 0;
            $product->image_id = $preservedImageId;
        }
    }

    private function resolveFabricProductIsActive(CatalogModel $model, CatalogFabricColor $color): bool
    {
        $fabricCollection = $color->fabricCollection;
        $collectionActive = $fabricCollection === null || (bool)$fabricCollection->is_active;

        return (bool)$model->is_active && (bool)$color->is_active && $collectionActive;
    }

    private function isSlugTaken(string $slug, ?int $excludeProductId = null): bool
    {
        $query = CatalogProduct::find()->where(['slug' => $slug]);
        if ($excludeProductId !== null) {
            $query->andWhere(['<>', 'id', $excludeProductId]);
        }

        return $query->exists();
    }

    private function buildProductHref(CatalogModel $model, string $productSlug): string
    {
        $collection = $model->collection;
        if ($collection !== null && trim((string)$collection->href) !== '') {
            return rtrim($collection->href, '/') . '/' . $productSlug;
        }

        return '/catalog/' . $productSlug;
    }

    private function applyListingFieldsFromModel(CatalogProduct $product, CatalogModel $model): void
    {
        $product->overall_size = $model->overall_size;
        $product->seat_depth = $model->seat_depth;
        $product->seat_height = $model->seat_height;
        $product->armrest_width = $model->armrest_width;
        $product->leg_height = $model->leg_height;
        $product->width_mm = $model->width_mm;
        $product->height_mm = $model->height_mm;
        $product->depth_mm = $model->depth_mm;
        $product->corner_depth_mm = $model->corner_depth_mm;
        $product->has_sleeping_place = (bool)$model->has_sleeping_place;
        $product->is_foldable = (bool)$model->is_foldable;
        $product->sleeping_place_width_mm = $model->sleeping_place_width_mm;
        $product->sleeping_place_depth_mm = $model->sleeping_place_depth_mm;
        CatalogListingValueParser::applyPriceAmountToAttributes($product, $product->price_display);
        if ($product->width_mm === null && $product->height_mm === null && $product->depth_mm === null) {
            CatalogListingValueParser::applyDimensionsToAttributes($product, $product->overall_size);
        }
    }
}
