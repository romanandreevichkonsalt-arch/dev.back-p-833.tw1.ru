<?php

namespace app\services\import\fabric;

use app\services\import\SpreadsheetFormatValidator;
use app\services\import\SpreadsheetSheetResolver;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class FabricRegistrySpreadsheetReader
{
    public const SHEET_FABRICS = 'Ткани и кожа';
    public const DATA_START_ROW = 5;
    public const COL_MATERIAL_KIND = 'B';
    public const COL_COLLECTION = 'C';
    public const COL_COLOR_NAME = 'D';
    public const COL_COMPOSITION = 'E';
    public const COL_PRICE_CATEGORY_A = 'F';
    public const COL_PRICE_CATEGORY_LINE1 = 'G';
    public const COL_TEXTURE = 'H';
    public const COL_COLOR = 'I';
    public const COL_MARTINDALE = 'J';
    public const COL_PROPERTIES = 'K';
    public const COL_ROLL_WIDTH = 'L';
    public const COL_DENSITY = 'M';
    public const COL_TEXTURE_URL = 'N';
    public const COL_IS_RECOMMENDED_FABRIC = 'O';
    public const COL_POSITION_NUMBER = 'P';
    public const COL_DESCRIPTION = 'Q';
    public const COL_COMMENT = 'R';

    /**
     * @return FabricRegistryRowDto[]
     */
    public function read(string $filePath): array
    {
        SpreadsheetFormatValidator::assertReadableExcel($filePath);

        $spreadsheet = IOFactory::load($filePath);
        $sheet = SpreadsheetSheetResolver::findSheet($spreadsheet, [self::SHEET_FABRICS]);
        if ($sheet !== null) {
            return $this->readRegistrySheet($sheet);
        }

        if (SpreadsheetSheetResolver::hasSheets($spreadsheet, [
            FabricClientSpreadsheetReader::SHEET_COLLECTIONS,
            FabricClientSpreadsheetReader::SHEET_COLORS,
        ])) {
            return (new FabricClientSpreadsheetReader())->read($spreadsheet);
        }

        throw SpreadsheetSheetResolver::sheetNotFoundException(
            self::SHEET_FABRICS,
            $spreadsheet,
            ['Ожидается лист «Ткани и кожа» (реестр v2) или листы «Коллекции» + «Цвета» (клиентский шаблон).'],
        );
    }

    /**
     * @return FabricRegistryRowDto[]
     */
    private function readRegistrySheet(Worksheet $sheet): array
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

    private function readRow(Worksheet $sheet, int $rowNumber): ?FabricRegistryRowDto
    {
        $collectionName = $this->cellValue($sheet, self::COL_COLLECTION, $rowNumber);
        $colorName = $this->cellValue($sheet, self::COL_COLOR_NAME, $rowNumber);

        if ($collectionName === '' && $colorName === '') {
            return null;
        }

        $materialKind = $this->cellValue($sheet, self::COL_MATERIAL_KIND, $rowNumber);

        return new FabricRegistryRowDto(
            rowNumber: $rowNumber,
            materialKind: $this->normalizeMaterialKind($materialKind),
            collectionName: $collectionName,
            colorName: FabricDesignCodeNormalizer::fromRegistry($colorName),
            composition: $this->nullableString($this->cellValue($sheet, self::COL_COMPOSITION, $rowNumber)),
            priceCategoryLabelA: $this->nullableString($this->cellValue($sheet, self::COL_PRICE_CATEGORY_A, $rowNumber)),
            priceCategoryLabelLine1: $this->nullableString($this->cellValue($sheet, self::COL_PRICE_CATEGORY_LINE1, $rowNumber)),
            texture: $this->nullableString($this->cellValue($sheet, self::COL_TEXTURE, $rowNumber)),
            colorLabel: $this->nullableString($this->cellValue($sheet, self::COL_COLOR, $rowNumber)),
            martindale: $this->parseInt($this->cellValue($sheet, self::COL_MARTINDALE, $rowNumber)),
            properties: $this->nullableString($this->cellValue($sheet, self::COL_PROPERTIES, $rowNumber)),
            rollWidthCm: $this->parseInt($this->cellValue($sheet, self::COL_ROLL_WIDTH, $rowNumber)),
            densityGsm: $this->parseInt($this->cellValue($sheet, self::COL_DENSITY, $rowNumber)),
            textureUrl: $this->nullableString($this->cellValue($sheet, self::COL_TEXTURE_URL, $rowNumber)),
            isRecommendedFabric: $this->parseBoolean($this->cellValue($sheet, self::COL_IS_RECOMMENDED_FABRIC, $rowNumber)),
            positionNumber: $this->parseInt($this->cellValue($sheet, self::COL_POSITION_NUMBER, $rowNumber)),
            description: $this->nullableString($this->cellValue($sheet, self::COL_DESCRIPTION, $rowNumber)),
            importComment: $this->nullableString($this->cellValue($sheet, self::COL_COMMENT, $rowNumber)),
        );
    }

    private function parseBoolean(string $value): bool
    {
        $value = mb_strtolower(trim($value));
        if ($value === '') {
            return false;
        }

        return in_array($value, ['1', 'yes', 'y', 'true', 'да', 'д', '+', 'x', '✓', 'v'], true)
            || str_contains($value, 'да');
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

    private function normalizeMaterialKind(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return \app\models\CatalogFabricCollection::MATERIAL_KIND_FABRIC;
        }

        foreach (\app\models\CatalogFabricCollection::MATERIAL_KINDS as $kind) {
            if (mb_strtolower($kind) === mb_strtolower($value)) {
                return $kind;
            }
        }

        return \app\models\CatalogFabricCollection::MATERIAL_KIND_FABRIC;
    }
}
