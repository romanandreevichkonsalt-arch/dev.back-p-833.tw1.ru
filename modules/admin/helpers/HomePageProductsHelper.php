<?php

namespace app\modules\admin\helpers;

use app\models\CatalogCollection;
use app\models\CatalogColor;
use app\models\CatalogFabricCollection;
use app\models\CatalogFabricColor;
use app\models\CatalogModel;
use app\models\CatalogProduct;
use app\models\CatalogSubcategory;
use app\services\catalog\CatalogUrlSlugResolver;
use app\services\catalog\ProductTitleBuilder;

class HomePageProductsHelper
{
    public const CARD_COUNT = 3;

    private const VISIBLE_SWATCHES = 4;

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function searchProducts(
        string $query,
        ?int $directionId = null,
        ?int $categoryId = null,
        ?int $subcategoryId = null,
        bool $catalogOnly = false,
        ?int $modelId = null,
    ): array {
        $query = trim($query);
        if (mb_strlen($query) < 3) {
            return [];
        }

        $tokens = self::searchTokens($query);
        if ($tokens === []) {
            return [];
        }

        $dbQuery = CatalogProduct::find()
            ->alias('p')
            ->distinct()
            ->with(['catalogModel.collection', 'fabricColor.fabricCollection', 'collection'])
            ->leftJoin(['m' => CatalogModel::tableName()], 'm.id = p.model_id')
            ->leftJoin(['fc' => CatalogFabricColor::tableName()], 'fc.id = p.fabric_color_id')
            ->leftJoin(['fcol' => CatalogFabricCollection::tableName()], 'fcol.id = fc.fabric_collection_id')
            ->leftJoin(['cc' => CatalogColor::tableName()], 'cc.id = fc.color_id');

        if ($catalogOnly) {
            $dbQuery->andWhere(['p.is_active' => true, 'p.is_custom' => false]);
        }

        if ($directionId !== null && $directionId > 0) {
            $dbQuery->leftJoin(['col' => CatalogCollection::tableName()], 'col.id = p.collection_id')
                ->andWhere(['col.direction_id' => $directionId]);
        }

        if ($categoryId !== null && $categoryId > 0) {
            $dbQuery->leftJoin(['sub' => CatalogSubcategory::tableName()], 'sub.id = p.subcategory_id')
                ->andWhere(['sub.category_id' => $categoryId]);
        }

        if ($subcategoryId !== null && $subcategoryId > 0) {
            $dbQuery->andWhere(['p.subcategory_id' => $subcategoryId]);
        }

        if ($modelId !== null && $modelId > 0) {
            $dbQuery->andWhere(['p.model_id' => $modelId]);
        }

        foreach ($tokens as $token) {
            $pattern = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $token) . '%';
            $dbQuery->andWhere([
                'or',
                ['like', 'p.title', $pattern, false],
                ['like', 'm.title', $pattern, false],
                ['like', 'fc.api_label', $pattern, false],
                ['like', 'fc.design_code', $pattern, false],
                ['like', 'cc.label', $pattern, false],
                ['like', 'fcol.name', $pattern, false],
            ]);
        }

        $products = $dbQuery
            ->orderBy(['p.title' => SORT_ASC, 'p.id' => SORT_ASC])
            ->limit(15)
            ->all();

        $items = [];
        foreach ($products as $product) {
            $items[] = self::pickerItemFromProduct($product);
        }

        return $items;
    }

    /**
     * @return string[]
     */
    private static function searchTokens(string $query): array
    {
        $parts = preg_split('/\s+/u', $query, -1, PREG_SPLIT_NO_EMPTY);
        if ($parts === false) {
            $parts = [$query];
        }

        $tokens = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }

            if (mb_strlen($part) >= 3) {
                $tokens[] = $part;
                continue;
            }

            if (count($parts) > 1 && mb_strlen($part) >= 2) {
                $tokens[] = $part;
            }
        }

        if ($tokens === [] && mb_strlen($query) >= 3) {
            $tokens[] = $query;
        }

        return $tokens;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function pickerItemByProductId(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $product = CatalogProduct::find()
            ->where(['id' => $id])
            ->with(['catalogModel.collection', 'catalogModel.fabricCollections', 'fabricColor.fabricCollection', 'collection'])
            ->one();

        if ($product === null) {
            return null;
        }

        return self::pickerItemFromProduct($product);
    }

    /**
     * @return array<string, mixed>
     */
    public static function pickerItemFromProduct(CatalogProduct $product): array
    {
        $model = $product->catalogModel;
        $color = $product->fabricColor;
        $productTitle = self::normalizeTitleSpacing($product->title);
        $colorLabel = trim((string)($color?->label ?? ''));

        return [
            'id' => (int)$product->id,
            'modelId' => $model !== null ? (int)$model->id : 0,
            'title' => $colorLabel !== '' ? $colorLabel : $productTitle,
            'productTitle' => $productTitle,
            'swatchStyle' => $color !== null ? $color->getSwatchCircleStyle() : '',
            'collection' => $model !== null
                ? self::buildCollectionLabel($model)
                : ($product->collection?->getDisplayName() ?? ''),
            'fabric' => self::fabricLineFromProduct($product),
            'price' => trim((string)$product->price_display),
            'swatchCount' => $model !== null ? self::buildSwatchCount($model) : '',
            'href' => self::productCardUrl($product),
            'previewName' => $model?->title ?? $productTitle,
        ];
    }

    /**
     * @param array<string, mixed>|null $item
     */
    public static function resolveProductIdFromApiItem(?array $item): ?int
    {
        if ($item === null) {
            return null;
        }

        $to = trim((string)($item['to'] ?? ''));
        if ($to !== '') {
            $product = CatalogProduct::find()->where(['href' => $to])->one();
            if ($product !== null) {
                return (int)$product->id;
            }

            $slug = basename(rtrim($to, '/'));
            if ($slug !== '') {
                $product = CatalogProduct::find()->where(['slug' => $slug])->one();
                if ($product !== null) {
                    return (int)$product->id;
                }
            }
        }

        $name = self::normalizeTitleSpacing((string)($item['name'] ?? ''));
        if ($name !== '') {
            $product = CatalogProduct::find()->where(['title' => $name])->one();
            if ($product !== null) {
                return (int)$product->id;
            }

            $product = CatalogProduct::find()
                ->where(['like', 'title', $name])
                ->orderBy(['id' => SORT_ASC])
                ->one();
            if ($product !== null) {
                return (int)$product->id;
            }
        }

        $modelId = self::resolveModelIdFromApiItem($item);
        if ($modelId !== null) {
            $product = CatalogProduct::find()
                ->where(['model_id' => $modelId])
                ->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC])
                ->one();
            if ($product !== null) {
                return (int)$product->id;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed>|null $item
     */
    public static function resolveModelIdFromApiItem(?array $item): ?int
    {
        if ($item === null) {
            return null;
        }

        $to = trim((string)($item['to'] ?? ''));
        if ($to !== '') {
            $slug = basename(rtrim($to, '/'));
            if ($slug !== '') {
                $model = CatalogModel::find()->where(['slug' => $slug])->one();
                if ($model !== null) {
                    return (int)$model->id;
                }
            }

            $product = CatalogProduct::find()->where(['href' => $to])->one();
            if ($product !== null && $product->model_id) {
                return (int)$product->model_id;
            }
        }

        $name = trim((string)($item['name'] ?? ''));
        if ($name !== '') {
            $model = CatalogModel::find()->where(['title' => $name])->one();
            if ($model !== null) {
                return (int)$model->id;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public static function buildHomeProductItemFromProduct(CatalogProduct $product, array $image, int $index): array
    {
        $model = $product->catalogModel;
        $name = $model?->title ?? self::normalizeTitleSpacing($product->title);

        if ($image['alt'] === '') {
            $image['alt'] = $name;
        }

        $layout = $index === 0 ? 'featured' : 'stacked';

        return [
            'number' => sprintf('%02d', $index + 1),
            'name' => $name,
            'collection' => $model !== null
                ? self::buildCollectionLabel($model)
                : ($product->collection?->getDisplayName() ?? ''),
            'fabric' => self::fabricLineFromProduct($product),
            'price' => trim((string)$product->price_display),
            'swatchCount' => $model !== null ? self::buildSwatchCount($model) : '',
            'layout' => $layout,
            'imagePosition' => 'center',
            'to' => self::productCardUrl($product),
            'image' => $image,
        ];
    }

    public static function productCardUrl(CatalogProduct $product): string
    {
        $slug = trim((string)$product->slug);
        if ($slug === '') {
            return trim((string)$product->href);
        }

        return (new CatalogUrlSlugResolver())->buildProductUrl($slug);
    }

    public static function normalizeTitleSpacing(string $title): string
    {
        return ProductTitleBuilder::normalizeSpacing($title);
    }

    public static function buildCollectionLabel(CatalogModel $model): string
    {
        $collection = $model->collection;
        if ($collection === null) {
            return '';
        }

        $label = trim((string)($collection->title ?: $collection->getDisplayName()));
        if ($label === '') {
            return '';
        }

        if (stripos($label, 'коллекция') === 0) {
            return $label;
        }

        return 'Коллекция ' . $label;
    }

    public static function fabricLineFromProduct(CatalogProduct $product): string
    {
        $color = $product->fabricColor;
        if ($color === null) {
            return '';
        }

        return self::fabricLineFromColor($color);
    }

    public static function buildSwatchCount(CatalogModel $model): string
    {
        $count = count($model->getLinkedActiveFabricColors());
        if ($count <= self::VISIBLE_SWATCHES) {
            return '';
        }

        return '+' . ($count - self::VISIBLE_SWATCHES);
    }

    private static function fabricLineFromColor(CatalogFabricColor $color): string
    {
        $label = trim((string)$color->label);
        if ($label !== '') {
            return 'Ткань: ' . $label;
        }

        $collectionName = trim((string)($color->fabricCollection?->name ?? ''));
        if ($collectionName !== '') {
            return 'Ткань: ' . $collectionName;
        }

        return '';
    }
}
