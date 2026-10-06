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

        if (class_exists(IOFactory::class)) {
            try {
                $type = IOFactory::identify($filePath);
                if (!in_array($type, ['Xlsx', 'Xls', 'Ods'], true)) {
                    throw new \InvalidArgumentException(
                        'Неверный формат файла (' . $type . '). Загрузите настоящий Excel (.xlsx).'
                    );
                }

                return;
            } catch (\InvalidArgumentException $e) {
                throw $e;
            } catch (\Throwable) {
                // PhpSpreadsheet недоступен или файл не распознан — пробуем .xlsx через ZIP ниже.
            }
        }

        SimpleXlsxSheetReader::assertZipXlsx($filePath);
    }
}
