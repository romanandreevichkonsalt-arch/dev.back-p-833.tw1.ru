<?php

namespace app\services\import\catalog;

use app\services\catalog\CatalogCategorySlug;
use app\services\catalog\CatalogFilterFunction;
use app\services\catalog\CatalogListingValueParser;

final class CatalogModelFilterFunctionApplier
{
    public function apply(object $model, string $filterFunction, string $categorySlug): void
    {
        if ($filterFunction === CatalogFilterFunction::WITH_SLEEPING && CatalogCategorySlug::isSofa($categorySlug)) {
            $model->has_sleeping_place = true;
            $model->is_foldable = false;

            return;
        }

        if ($filterFunction === CatalogFilterFunction::FOLDABLE && CatalogCategorySlug::isArmchair($categorySlug)) {
            $model->is_foldable = true;
            $model->has_sleeping_place = false;

            return;
        }

        if ($filterFunction === CatalogFilterFunction::NO_SLEEPING) {
            return;
        }
    }

    public function applySleepingPlaceSize(
        object $model,
        string $filterFunction,
        string $categorySlug,
        ?string $rawSize,
    ): void {
        $canHaveSize = ($filterFunction === CatalogFilterFunction::WITH_SLEEPING && CatalogCategorySlug::isSofa($categorySlug))
            || ($filterFunction === CatalogFilterFunction::FOLDABLE && CatalogCategorySlug::isArmchair($categorySlug));

        if ($filterFunction === CatalogFilterFunction::NO_SLEEPING) {
            $model->sleeping_place_width_mm = null;
            $model->sleeping_place_depth_mm = null;

            return;
        }

        if (!$canHaveSize) {
            if ($filterFunction === CatalogFilterFunction::WITH_SLEEPING
                || $filterFunction === CatalogFilterFunction::FOLDABLE
            ) {
                $model->sleeping_place_width_mm = null;
                $model->sleeping_place_depth_mm = null;
            }

            return;
        }

        if ($rawSize === null || trim($rawSize) === '') {
            return;
        }

        $parsed = CatalogListingValueParser::parseSleepingPlaceSizeMm($rawSize);
        if ($parsed === null) {
            return;
        }

        $model->sleeping_place_width_mm = $parsed['width'];
        $model->sleeping_place_depth_mm = $parsed['depth'];
    }
}
