<?php

namespace app\services\catalog;

final class CatalogListingValueParser
{
    private const SIZE_SEPARATOR_PATTERN = '[×xXх*]';

    /**
     * @return array{width: int, height: int, depth: int, cornerDepth?: int}|null
     */
    public static function parseOverallSizeMm(?string $overallSize): ?array
    {
        if ($overallSize === null || trim($overallSize) === '') {
            return null;
        }

        $pattern = '/(\d+)\s*' . self::SIZE_SEPARATOR_PATTERN . '\s*(\d+)\s*'
            . self::SIZE_SEPARATOR_PATTERN . '\s*(\d+)'
            . '(?:\s*' . self::SIZE_SEPARATOR_PATTERN . '\s*(\d+))?/u';

        if (!preg_match($pattern, trim($overallSize), $matches)) {
            return null;
        }

        $result = [
            'width' => (int)$matches[1],
            'height' => (int)$matches[2],
            'depth' => (int)$matches[3],
        ];

        if (isset($matches[4]) && $matches[4] !== '') {
            $result['cornerDepth'] = (int)$matches[4];
        }

        return $result;
    }

    public static function parsePriceAmount(?string $display): ?int
    {
        if ($display === null || trim($display) === '') {
            return null;
        }

        $digits = preg_replace('/[^\d]/', '', $display);

        return $digits !== '' ? (int)$digits : null;
    }

    /**
     * @deprecated Use parseFilterFunction() for import column «Функция (для фильтра)».
     */
    public static function parseSleepingPlaceFilter(?string $value): bool
    {
        return self::parseFilterFunction($value) === CatalogFilterFunction::WITH_SLEEPING;
    }

    public static function parseFilterFunction(?string $value): string
    {
        if ($value === null) {
            return CatalogFilterFunction::NONE;
        }

        $normalized = mb_strtolower(trim($value), 'UTF-8');
        if ($normalized === '') {
            return CatalogFilterFunction::NONE;
        }

        if (str_contains($normalized, 'без спальн')) {
            return CatalogFilterFunction::NO_SLEEPING;
        }

        if (str_contains($normalized, 'расклад')) {
            return CatalogFilterFunction::FOLDABLE;
        }

        if (str_contains($normalized, 'со спальн') || str_contains($normalized, 'спальн')) {
            return CatalogFilterFunction::WITH_SLEEPING;
        }

        return CatalogFilterFunction::NONE;
    }

    /**
     * @return array{width: int, depth: int}|null
     */
    public static function parseSleepingPlaceSizeMm(?string $value): ?array
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $pattern = '/(\d+)\s*[×xXх*]\s*(\d+)/u';
        if (!preg_match($pattern, trim($value), $matches)) {
            return null;
        }

        return [
            'width' => (int)$matches[1],
            'depth' => (int)$matches[2],
        ];
    }

    public static function buildOverallSizeString(
        ?int $width,
        ?int $height,
        ?int $depth,
        ?int $cornerDepth = null
    ): ?string {
        if ($width === null || $height === null || $depth === null) {
            return null;
        }

        $parts = [(string)$width, (string)$height, (string)$depth];
        if ($cornerDepth !== null) {
            $parts[] = (string)$cornerDepth;
        }

        return implode('×', $parts);
    }

    public static function applyDimensionsToAttributes(object $model, ?string $overallSize): void
    {
        $parsed = self::parseOverallSizeMm($overallSize);
        $model->width_mm = $parsed['width'] ?? null;
        $model->height_mm = $parsed['height'] ?? null;
        $model->depth_mm = $parsed['depth'] ?? null;
        if (property_exists($model, 'corner_depth_mm')) {
            $model->corner_depth_mm = $parsed['cornerDepth'] ?? null;
        }
    }

    public static function syncDimensionAttributes(object $model): void
    {
        $overallSize = trim((string)($model->overall_size ?? ''));
        $hasSplitDimensions = $model->width_mm !== null
            && $model->height_mm !== null
            && $model->depth_mm !== null;

        if ($hasSplitDimensions) {
            $cornerDepth = property_exists($model, 'corner_depth_mm') ? $model->corner_depth_mm : null;
            $model->overall_size = self::buildOverallSizeString(
                (int)$model->width_mm,
                (int)$model->height_mm,
                (int)$model->depth_mm,
                $cornerDepth !== null ? (int)$cornerDepth : null
            );

            return;
        }

        if ($overallSize !== '') {
            self::applyDimensionsToAttributes($model, $overallSize);
        }
    }

    public static function applyPriceAmountToAttributes(object $model, ?string $priceDisplay): void
    {
        $model->price_amount = self::parsePriceAmount($priceDisplay);
    }
}
