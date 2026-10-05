<?php

namespace app\services\import\surface;

use app\models\CatalogSurfaceMaterial;

class CatalogSurfaceMaterialTypeNormalizer
{
    public static function defaultType(): string
    {
        return CatalogSurfaceMaterial::TYPE_WOOD;
    }

    public static function normalize(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return self::defaultType();
        }

        foreach (CatalogSurfaceMaterial::MATERIAL_TYPES as $type) {
            if (mb_strtolower($type) === mb_strtolower($value)) {
                return $type;
            }
        }

        return $value;
    }
}
