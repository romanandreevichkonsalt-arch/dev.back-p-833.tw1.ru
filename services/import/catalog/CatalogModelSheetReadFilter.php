<?php

namespace app\services\import\catalog;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

/**
 * Читает лист «Модели»: строки 1–30 (поиск заголовка) и данные; колонки A–BZ.
 */
final class CatalogModelSheetReadFilter implements IReadFilter
{
    private const FIRST_COLUMN_INDEX = 1;
    private const LAST_COLUMN_INDEX = 78;

    public function readCell($columnAddress, $row, $worksheetName = ''): bool
    {
        if ($row < 1) {
            return false;
        }

        $columnIndex = Coordinate::columnIndexFromString(strtoupper((string)$columnAddress));

        return $columnIndex >= self::FIRST_COLUMN_INDEX
            && $columnIndex <= self::LAST_COLUMN_INDEX;
    }
}
