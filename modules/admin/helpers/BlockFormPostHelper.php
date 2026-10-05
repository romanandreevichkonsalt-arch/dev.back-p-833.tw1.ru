<?php

namespace app\modules\admin\helpers;

class BlockFormPostHelper
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function rows(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        return array_values($raw);
    }

    /**
     * @param array<string, mixed> $row
     * @return array{src: string, alt: string}|null
     */
    public static function imageFromRow(array $row, string $srcKey = 'image_src', string $altKey = 'image_alt'): ?array
    {
        $src = trim((string)($row[$srcKey] ?? ''));
        if ($src === '') {
            return null;
        }

        return [
            'src' => $src,
            'alt' => trim((string)($row[$altKey] ?? '')),
        ];
    }

    public static function boolValue(mixed $value): bool
    {
        return $value === '1' || $value === 1 || $value === true || $value === 'true' || $value === 'on';
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    public static function filterRows(array $rows, callable $isEmpty): array
    {
        $result = [];
        foreach ($rows as $row) {
            if (!$isEmpty($row)) {
                $result[] = $row;
            }
        }

        return $result;
    }
}
