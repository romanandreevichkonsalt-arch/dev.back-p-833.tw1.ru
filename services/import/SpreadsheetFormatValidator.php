<?php

namespace app\services\import;

use PhpOffice\PhpSpreadsheet\IOFactory;

class SpreadsheetFormatValidator
{
    /**
     * @throws \InvalidArgumentException
     */
    public static function assertReadableExcel(string $filePath): void
    {
        if (!is_file($filePath)) {
            throw new \InvalidArgumentException('Файл не найден.');
        }

        if (filesize($filePath) === 0) {
            throw new \InvalidArgumentException('Файл пустой. Повторите загрузку .xlsx.');
        }

        try {
            $type = IOFactory::identify($filePath);
        } catch (\Throwable $e) {
            throw new \InvalidArgumentException(
                'Не удалось прочитать Excel-файл. Убедитесь, что загружаете .xlsx, а не HTML или другой формат.'
            );
        }

        if (!in_array($type, ['Xlsx', 'Xls', 'Ods'], true)) {
            throw new \InvalidArgumentException(
                'Неверный формат файла (' . $type . '). Загрузите настоящий Excel (.xlsx).'
            );
        }
    }
}
