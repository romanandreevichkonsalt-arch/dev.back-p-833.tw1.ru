<?php

namespace app\services\catalog;

use app\helpers\SlugHelper;
use app\models\CatalogFabricColor;
use app\models\CatalogModel;

class ProductTitleBuilder
{
    public static function build(CatalogModel $model, CatalogFabricColor $color): string
    {
        $parts = [self::resolveTypeLabel($model)];

        $collectionName = trim((string)($model->collection?->name ?? $model->collection?->title ?? ''));
        if ($collectionName !== '') {
            $parts[] = $collectionName;
        }

        $colorLabel = $color->getProductColorLabel();
        if ($colorLabel !== '') {
            $parts[] = $colorLabel;
        }

        $fabricCollectionName = self::resolveFabricCollectionLabel($color);
        if ($fabricCollectionName !== '') {
            $parts[] = $fabricCollectionName;
        }

        $designCode = self::resolveFabricDesignCode($color);
        if ($designCode !== '') {
            $parts[] = $designCode;
        }

        return self::normalizeSpacing(trim(implode(' ', array_filter($parts, static fn (string $p): bool => $p !== ''))));
    }

    public static function buildDefault(CatalogModel $model): string
    {
        $collectionName = trim((string)($model->collection?->name ?? $model->collection?->title ?? ''));
        $typeLabel = self::resolveTypeLabel($model);
        $parts = array_filter([$typeLabel, $collectionName, 'кастом'], static fn (string $p): bool => $p !== '');

        return self::normalizeSpacing(implode(' ', $parts));
    }

    public static function buildSlugFromTitle(string $title): string
    {
        return SlugHelper::slugify(self::normalizeSpacing($title));
    }

    public static function buildSlug(CatalogModel $model, CatalogFabricColor $color): string
    {
        return self::buildSlugFromTitle(self::build($model, $color));
    }

    public static function buildDefaultSlug(CatalogModel $model): string
    {
        return self::buildSlugFromTitle(self::buildDefault($model));
    }

    public static function resolveFabricCollectionLabel(CatalogFabricColor $color): string
    {
        return trim((string)($color->fabricCollection?->name ?? ''));
    }

    public static function resolveFabricDesignCode(CatalogFabricColor $color): string
    {
        return trim((string)$color->design_code);
    }

    public static function resolveTypeLabel(CatalogModel $model): string
    {
        $subcategoryLabel = trim((string)($model->subcategory?->label ?? ''));
        if ($subcategoryLabel !== '') {
            return $subcategoryLabel;
        }

        $categoryLabel = trim((string)($model->category?->label ?? ''));

        if ($categoryLabel === 'Модули') {
            return 'Модуль';
        }

        $title = trim((string)$model->title);
        if ($title !== '') {
            return $title;
        }

        return $categoryLabel;
    }

    public static function normalizeSpacing(string $title): string
    {
        $title = str_replace([' — ', ' – ', ' - '], ' ', $title);
        $title = preg_replace('/\s+/u', ' ', $title) ?? $title;

        return trim($title);
    }
}
