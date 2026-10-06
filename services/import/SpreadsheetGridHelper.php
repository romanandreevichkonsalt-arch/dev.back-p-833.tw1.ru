<?php

namespace app\services\import;

/**
 * Чтение ячеек из grid SimpleXlsxSheetReader (1-based rows, column letters).
 */
class SpreadsheetGridHelper
{
    /**
     * @param array<int, array<string, string>> $grid
     */
    public static function cellValue(array $grid, string $column, int $rowNumber): string
    {
        $value = $grid[$rowNumber][$column] ?? '';
        if ($value === '') {
            return '';
        }

        if (is_numeric($value)) {
            $float = (float)$value;
            if (floor($float) === $float) {
                return (string)(int)$float;
            }

            return rtrim(rtrim(sprintf('%.10F', $float), '0'), '.');
        }

        return trim($value);
    }

    /**
     * @param array<int, array<string, string>> $grid
     */
    public static function highestDataRow(array $grid): int
    {
        if ($grid === []) {
            return 0;
        }

        return max(array_keys($grid));
    }
}
