<?php

namespace app\services\import\fabric;

use app\services\import\SimpleXlsxSheetReader;
use app\services\import\SpreadsheetFormatValidator;
use app\services\import\SpreadsheetGridHelper;
use app\services\import\SpreadsheetSheetResolver;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Yii;

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
    public function read(string $filePath, int $startIndex = 0): array
    {
        SpreadsheetFormatValidator::assertReadableExcel($filePath);

        if ($startIndex > 0) {
            $cached = FabricRegistrySpreadsheetRowCache::loadIfFresh($filePath);
            if ($cached !== null) {
                Yii::info(
                    sprintf('[PARSE] resume from row cache, startIndex=%d, total=%d', $startIndex, count($cached)),
                    'fabric-import'
                );

                return array_slice($cached, $startIndex);
            }
        }

        $parseStarted = microtime(true);
        if (class_exists(IOFactory::class)) {
            try {
                $rows = $this->readViaPhpSpreadsheet($filePath);
            } catch (\Throwable) {
                // Битая установка PhpSpreadsheet или нестандартный файл — читаем .xlsx напрямую.
                $rows = $this->readViaSimpleXlsx($filePath);
            }
        } else {
            $rows = $this->readViaSimpleXlsx($filePath);
        }

        FabricRegistrySpreadsheetRowCache::save($filePath, $rows);
        Yii::info(
            sprintf('[PARSE] rows=%d ms=%d', count($rows), (int)round((microtime(true) - $parseStarted) * 1000)),
            'fabric-import'
        );

        if ($startIndex > 0) {
            return array_slice($rows, $startIndex);
        }

        return $rows;
    }

    /**
     * @return FabricRegistryRowDto[]
     */
    private function readViaPhpSpreadsheet(string $filePath): array
    {
        $format = $this->detectWorkbookFormat($filePath);
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $readerType = $extension === 'xls' ? 'Xls' : 'Xlsx';
        $reader = IOFactory::createReader($readerType);
        $reader->setReadDataOnly(true);

        if ($format === 'client') {
            $reader->setLoadSheetsOnly([
                FabricClientSpreadsheetReader::SHEET_COLLECTIONS,
                FabricClientSpreadsheetReader::SHEET_COLORS,
            ]);
            $spreadsheet = $reader->load($filePath);

            return (new FabricClientSpreadsheetReader())->read($spreadsheet);
        }

        $reader->setLoadSheetsOnly([self::SHEET_FABRICS]);
        $reader->setReadFilter(new FabricRegistrySheetReadFilter());
        $spreadsheet = $reader->load($filePath);
        $sheet = SpreadsheetSheetResolver::findSheet($spreadsheet, [self::SHEET_FABRICS]);
        if ($sheet === null) {
            throw SpreadsheetSheetResolver::sheetNotFoundException(
                self::SHEET_FABRICS,
                $spreadsheet,
                ['Ожидается лист «Ткани и кожа» (реестр v2) или листы «Коллекции» + «Цвета» (клиентский шаблон).'],
            );
        }

        return $this->readRegistrySheet($sheet);
    }

    /**
     * @return 'registry'|'client'
     */
    private function detectWorkbookFormat(string $filePath): string
    {
        $reader = new SimpleXlsxSheetReader();
        $names = $reader->listSheetNames($filePath);
        $normalized = array_map(
            static fn (string $name): string => SpreadsheetSheetResolver::normalizeName($name),
            $names
        );

        $registryKey = SpreadsheetSheetResolver::normalizeName(self::SHEET_FABRICS);
        if (in_array($registryKey, $normalized, true)) {
            return 'registry';
        }

        $collectionsKey = SpreadsheetSheetResolver::normalizeName(FabricClientSpreadsheetReader::SHEET_COLLECTIONS);
        $colorsKey = SpreadsheetSheetResolver::normalizeName(FabricClientSpreadsheetReader::SHEET_COLORS);
        if (in_array($collectionsKey, $normalized, true) && in_array($colorsKey, $normalized, true)) {
            return 'client';
        }

        return 'registry';
    }

    /**
     * @return FabricRegistryRowDto[]
     */
    private function readViaSimpleXlsx(string $filePath): array
    {
        $reader = new SimpleXlsxSheetReader();
        $sheetNames = $reader->listSheetNames($filePath);
        $format = $this->detectWorkbookFormat($filePath);

        if ($format === 'client') {
            $grids = $reader->readSheetsGrids($filePath, [
                FabricClientSpreadsheetReader::SHEET_COLLECTIONS,
                FabricClientSpreadsheetReader::SHEET_COLORS,
            ]);
            $collectionsKey = SpreadsheetSheetResolver::normalizeName(FabricClientSpreadsheetReader::SHEET_COLLECTIONS);
            $colorsKey = SpreadsheetSheetResolver::normalizeName(FabricClientSpreadsheetReader::SHEET_COLORS);
            if (isset($grids[$collectionsKey], $grids[$colorsKey])) {
                return (new FabricClientSpreadsheetReader())->readFromGrids(
                    $grids[$collectionsKey],
                    $grids[$colorsKey]
                );
            }
        }

        $grids = $reader->readSheetsGrids($filePath, [self::SHEET_FABRICS]);
        $registryKey = SpreadsheetSheetResolver::normalizeName(self::SHEET_FABRICS);
        if (isset($grids[$registryKey])) {
            return $this->readRegistryGrid($grids[$registryKey]);
        }

        throw SpreadsheetSheetResolver::sheetNotFoundExceptionFromNames(
            self::SHEET_FABRICS,
            $sheetNames,
            ['Ожидается лист «Ткани и кожа» (реестр v2) или листы «Коллекции» + «Цвета» (клиентский шаблон).'],
        );
    }

    /**
     * @param array<int, array<string, string>> $grid
     * @return FabricRegistryRowDto[]
     */
    private function readRegistryGrid(array $grid): array
    {
        $rows = [];
        $highestRow = SpreadsheetGridHelper::highestDataRow($grid);

        for ($rowNumber = self::DATA_START_ROW; $rowNumber <= $highestRow; $rowNumber++) {
            $dto = $this->readRowFromGrid($grid, $rowNumber);
            if ($dto === null) {
                continue;
            }

            $rows[] = $dto;
        }

        return $rows;
    }

    /**
     * @param array<int, array<string, string>> $grid
     */
    private function readRowFromGrid(array $grid, int $rowNumber): ?FabricRegistryRowDto
    {
        $collectionName = SpreadsheetGridHelper::cellValue($grid, self::COL_COLLECTION, $rowNumber);
        $colorName = SpreadsheetGridHelper::cellValue($grid, self::COL_COLOR_NAME, $rowNumber);

        if ($collectionName === '' && $colorName === '') {
            return null;
        }

        $materialKind = SpreadsheetGridHelper::cellValue($grid, self::COL_MATERIAL_KIND, $rowNumber);

        return new FabricRegistryRowDto(
            rowNumber: $rowNumber,
            materialKind: $this->normalizeMaterialKind($materialKind),
            collectionName: $collectionName,
            colorName: FabricDesignCodeNormalizer::fromRegistry($colorName),
            composition: $this->nullableString(SpreadsheetGridHelper::cellValue($grid, self::COL_COMPOSITION, $rowNumber)),
            priceCategoryLabelA: $this->nullableString(SpreadsheetGridHelper::cellValue($grid, self::COL_PRICE_CATEGORY_A, $rowNumber)),
            priceCategoryLabelLine1: $this->nullableString(SpreadsheetGridHelper::cellValue($grid, self::COL_PRICE_CATEGORY_LINE1, $rowNumber)),
            texture: $this->nullableString(SpreadsheetGridHelper::cellValue($grid, self::COL_TEXTURE, $rowNumber)),
            colorLabel: $this->nullableString(SpreadsheetGridHelper::cellValue($grid, self::COL_COLOR, $rowNumber)),
            martindale: $this->parseInt(SpreadsheetGridHelper::cellValue($grid, self::COL_MARTINDALE, $rowNumber)),
            properties: $this->nullableString(SpreadsheetGridHelper::cellValue($grid, self::COL_PROPERTIES, $rowNumber)),
            rollWidthCm: $this->parseInt(SpreadsheetGridHelper::cellValue($grid, self::COL_ROLL_WIDTH, $rowNumber)),
            densityGsm: $this->parseInt(SpreadsheetGridHelper::cellValue($grid, self::COL_DENSITY, $rowNumber)),
            textureUrl: $this->nullableString(SpreadsheetGridHelper::cellValue($grid, self::COL_TEXTURE_URL, $rowNumber)),
            isRecommendedFabric: $this->parseBoolean(SpreadsheetGridHelper::cellValue($grid, self::COL_IS_RECOMMENDED_FABRIC, $rowNumber)),
            positionNumber: $this->parseInt(SpreadsheetGridHelper::cellValue($grid, self::COL_POSITION_NUMBER, $rowNumber)),
            description: $this->nullableString(SpreadsheetGridHelper::cellValue($grid, self::COL_DESCRIPTION, $rowNumber)),
            importComment: $this->nullableString(SpreadsheetGridHelper::cellValue($grid, self::COL_COMMENT, $rowNumber)),
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
