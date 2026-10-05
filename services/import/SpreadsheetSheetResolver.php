<?php

namespace app\services\import;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SpreadsheetSheetResolver
{
    /**
     * @param list<string> $candidates
     */
    public static function findSheet(Spreadsheet $spreadsheet, array $candidates): ?Worksheet
    {
        foreach ($candidates as $candidate) {
            $sheet = $spreadsheet->getSheetByName($candidate);
            if ($sheet !== null) {
                return $sheet;
            }
        }

        $normalizedCandidates = [];
        foreach ($candidates as $candidate) {
            $normalizedCandidates[self::normalizeName($candidate)] = $candidate;
        }

        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            $normalizedTitle = self::normalizeName($sheet->getTitle());
            if (array_key_exists($normalizedTitle, $normalizedCandidates)) {
                return $sheet;
            }
        }

        return null;
    }

    /**
     * @param list<string> $requiredNames
     */
    public static function hasSheets(Spreadsheet $spreadsheet, array $requiredNames): bool
    {
        foreach ($requiredNames as $name) {
            if (self::findSheet($spreadsheet, [$name]) === null) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<string> $hints
     */
    public static function sheetNotFoundException(
        string $expectedLabel,
        Spreadsheet $spreadsheet,
        array $hints = []
    ): \InvalidArgumentException {
        $available = array_map(
            static fn (string $name): string => '«' . $name . '»',
            $spreadsheet->getSheetNames()
        );
        $message = 'Лист «' . $expectedLabel . '» не найден в файле.';
        if ($available !== []) {
            $message .= ' Найдены листы: ' . implode(', ', $available) . '.';
        }
        if ($hints !== []) {
            $message .= ' ' . implode(' ', $hints);
        }

        return new \InvalidArgumentException($message);
    }

    public static function normalizeName(string $name): string
    {
        $name = str_replace("\xc2\xa0", ' ', $name);
        $name = preg_replace('/\s+/u', ' ', trim($name)) ?? trim($name);

        return mb_strtolower($name);
    }
}
