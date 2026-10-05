<?php

namespace app\services\import\fabric;

use app\services\import\SpreadsheetSheetResolver;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class FabricClientSpreadsheetReader
{
    public const SHEET_COLLECTIONS = 'Коллекции';
    public const SHEET_COLORS = 'Цвета';
    private const DATA_START_ROW = 2;

    /**
     * @return FabricRegistryRowDto[]
     */
    public function read(Spreadsheet $spreadsheet): array
    {
        $collectionsSheet = SpreadsheetSheetResolver::findSheet($spreadsheet, [self::SHEET_COLLECTIONS]);
        $colorsSheet = SpreadsheetSheetResolver::findSheet($spreadsheet, [self::SHEET_COLORS]);
        if ($collectionsSheet === null || $colorsSheet === null) {
            throw SpreadsheetSheetResolver::sheetNotFoundException(
                FabricRegistrySpreadsheetReader::SHEET_FABRICS,
                $spreadsheet,
                ['Ожидается лист «Ткани и кожа» или пара листов «Коллекции» + «Цвета».'],
            );
        }

        $collections = $this->readCollections($collectionsSheet);
        $rows = [];
        $highestRow = (int)$colorsSheet->getHighestDataRow();

        for ($rowNumber = self::DATA_START_ROW; $rowNumber <= $highestRow; $rowNumber++) {
            $collectionName = $this->cellValue($colorsSheet, 'A', $rowNumber);
            $colorName = $this->cellValue($colorsSheet, 'B', $rowNumber);
            if ($collectionName === '' && $colorName === '') {
                continue;
            }

            $collectionKey = SpreadsheetSheetResolver::normalizeName($collectionName);
            $collection = $collections[$collectionKey] ?? null;

            $rows[] = new FabricRegistryRowDto(
                rowNumber: $rowNumber,
                materialKind: $collection['materialKind'] ?? \app\models\CatalogFabricCollection::MATERIAL_KIND_FABRIC,
                collectionName: $collectionName,
                colorName: FabricDesignCodeNormalizer::fromRegistry($colorName),
                composition: $collection['composition'] ?? null,
                priceCategoryLabelA: $collection['priceCategoryLabelA'] ?? null,
                priceCategoryLabelLine1: $collection['priceCategoryLabelLine1'] ?? null,
                texture: $collection['texture'] ?? null,
                colorLabel: $this->nullableString($this->cellValue($colorsSheet, 'C', $rowNumber)),
                martindale: $collection['martindale'] ?? null,
                properties: $collection['properties'] ?? null,
                rollWidthCm: $collection['rollWidthCm'] ?? null,
                densityGsm: $collection['densityGsm'] ?? null,
                textureUrl: $this->nullableString($this->cellValue($colorsSheet, 'D', $rowNumber)),
                isRecommendedFabric: false,
                positionNumber: null,
                description: $collection['description'] ?? null,
                importComment: $this->nullableString($this->cellValue($colorsSheet, 'E', $rowNumber)),
            );
        }

        return $rows;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function readCollections(Worksheet $sheet): array
    {
        $collections = [];
        $highestRow = (int)$sheet->getHighestDataRow();

        for ($rowNumber = self::DATA_START_ROW; $rowNumber <= $highestRow; $rowNumber++) {
            $collectionName = $this->cellValue($sheet, 'B', $rowNumber);
            if ($collectionName === '') {
                continue;
            }

            $collections[SpreadsheetSheetResolver::normalizeName($collectionName)] = [
                'materialKind' => $this->normalizeMaterialKind($this->cellValue($sheet, 'A', $rowNumber)),
                'composition' => $this->nullableString($this->cellValue($sheet, 'C', $rowNumber)),
                'texture' => $this->nullableString($this->cellValue($sheet, 'D', $rowNumber)),
                'martindale' => $this->parseInt($this->cellValue($sheet, 'E', $rowNumber)),
                'properties' => $this->nullableString($this->cellValue($sheet, 'F', $rowNumber)),
                'rollWidthCm' => $this->parseInt($this->cellValue($sheet, 'G', $rowNumber)),
                'densityGsm' => $this->parseInt($this->cellValue($sheet, 'H', $rowNumber)),
                'description' => $this->nullableString($this->cellValue($sheet, 'I', $rowNumber)),
                'priceCategoryLabelA' => $this->nullableString($this->cellValue($sheet, 'J', $rowNumber)),
                'priceCategoryLabelLine1' => $this->nullableString($this->cellValue($sheet, 'K', $rowNumber)),
            ];
        }

        return $collections;
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
