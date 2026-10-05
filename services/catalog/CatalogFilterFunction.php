<?php

namespace app\services\catalog;

final class CatalogFilterFunction
{
    public const NONE = 'none';
    public const NO_SLEEPING = 'no_sleeping';
    public const WITH_SLEEPING = 'with_sleeping';
    public const FOLDABLE = 'foldable';

    public static function isKnown(string $value): bool
    {
        return in_array($value, [
            self::NONE,
            self::NO_SLEEPING,
            self::WITH_SLEEPING,
            self::FOLDABLE,
        ], true);
    }
}
