<?php

namespace app\services\import\fabric;

class FabricColorNameValidator
{
    public static function isImportableColorName(string $value): bool
    {
        $value = trim($value);

        return $value !== '' && mb_strlen($value) <= 48;
    }

    public static function normalizeLabel(string $value): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
        if ($value === '') {
            return '';
        }

        return mb_strtoupper(mb_substr($value, 0, 1)) . mb_substr($value, 1);
    }
}
