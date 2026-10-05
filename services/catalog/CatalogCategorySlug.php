<?php

namespace app\services\catalog;

final class CatalogCategorySlug
{
    public static function isSofa(string $slug): bool
    {
        return in_array($slug, ['sofa', 'divan'], true);
    }

    public static function isArmchair(string $slug): bool
    {
        return in_array($slug, ['armchair', 'kreslo'], true);
    }
}
