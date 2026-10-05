<?php

namespace app\services\import\surface;

use app\services\import\SpreadsheetFormatValidator;
use app\services\import\SpreadsheetSheetResolver;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SurfaceMaterialRegistrySpreadsheetReader
{
    public const SHEET = 'Дерево и металл';
    public const DATA_START_ROW = 4;

    public const COL_NUMBER = 'A';
    public const COL_TYPE = 'B';
    public const COL_NAME = 'C';
    public const COL_MODELS = 'D';
    public const COL_PHOTO = 'E';
    public const COL_TEXTURE = 'F';
    public const COL_DESCRIPTION = 'G';

    /**
     * @return SurfaceMaterialRegistryRowDto[]
     */
    public function read(string $filePath): array
    {
        SpreadsheetFormatValidator::assertReadableExcel($filePath);

        $spreadsheet = IOFactory::load($filePath);
        $sheet = SpreadsheetSheetResolver::findSheet($spreadsheet, [self::SHEET]);
        if ($sheet === null) {
            throw SpreadsheetSheetResolver::sheetNotFoundException(
                self::SHEET,
                $spreadsheet,
                ['Ожидается лист «Дерево и металл» (реестр v2).'],
            );
        }

        return $this->readSheet($sheet);
    }

    /**
     * @return SurfaceMaterialRegistryRowDto[]
     */
    private function readSheet(Worksheet $sheet): array
    {
        $rows = [];
        $highestRow = (int)$sheet->getHighestDataRow();

        for ($rowNumber = self::DATA_START_ROW; $rowNumber <= $highestRow; $rowNumber++) {
            $dto = $this->readRow($sheet, $rowNumber);
            if ($dto === null) {
                continue;
            }

            $rows[] = $dto;
        }

        return $rows;
    }

    private function readRow(Worksheet $sheet, int $rowNumber): ?SurfaceMaterialRegistryRowDto
    {
        $name = $this->cellValue($sheet, self::COL_NAME, $rowNumber);
        $materialType = $this->cellValue($sheet, self::COL_TYPE, $rowNumber);

        if ($name === '' && $materialType === '') {
            return null;
        }

        if ($materialType === '') {
            $materialType = CatalogSurfaceMaterialTypeNormalizer::defaultType();
        }

        return new SurfaceMaterialRegistryRowDto(
            rowNumber: $rowNumber,
            registryNumber: $this->parseInt($this->cellValue($sheet, self::COL_NUMBER, $rowNumber)),
            materialType: CatalogSurfaceMaterialTypeNormalizer::normalize($materialType),
            name: $name,
            appliedModelsText: $this->nullableString($this->cellValue($sheet, self::COL_MODELS, $rowNumber)),
            photoUrl: $this->nullableString($this->cellValue($sheet, self::COL_PHOTO, $rowNumber)),
            textureUrl: $this->nullableString($this->cellValue($sheet, self::COL_TEXTURE, $rowNumber)),
            description: $this->nullableString($this->cellValue($sheet, self::COL_DESCRIPTION, $rowNumber)),
        );
    }

    private function cellValue(Worksheet $sheet, string $column, int $rowNumber): string
    {
        $value = $sheet->getCell($column . $rowNumber)->getCalculatedValue();
        if ($value === null) {
            return '';
        }

        if (is_int($value) || is_float($value)) {
            if (is_float($value) && floor($value) === $value) {
                return (string)(int)$value;
            }

            return rtrim(rtrim(sprintf('%.10F', (float)$value), '0'), '.');
        }

        return trim((string)$value);
    }

    private function nullableString(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function parseInt(string $value): ?int
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/-?\d+/', str_replace([' ', ','], ['', '.'], $value), $matches) !== 1) {
            return null;
        }

        return (int)$matches[0];
    }
}
