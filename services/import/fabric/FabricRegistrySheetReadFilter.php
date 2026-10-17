<?php

namespace app\services\import\fabric;

use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

/**
 * Читает только строки данных реестра (с 5-й) и колонки A–X листа «Ткани и кожа».
 */
final class FabricRegistrySheetReadFilter implements IReadFilter
{
    private const FIRST_COLUMN = 'A';
    private const LAST_COLUMN = 'X';

    public function __construct(
        private readonly int $startRow = FabricRegistrySpreadsheetReader::DATA_START_ROW,
    ) {
    }

    public function readCell($columnAddress, $row, $worksheetName = ''): bool
    {
        if ($row < $this->startRow) {
            return false;
        }

        $column = strtoupper((string)$columnAddress);

        return strcmp($column, self::FIRST_COLUMN) >= 0
            && strcmp($column, self::LAST_COLUMN) <= 0;
    }
}
