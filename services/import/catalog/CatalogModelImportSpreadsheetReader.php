<?php

namespace app\services\import\catalog;

use PhpOffice\PhpSpreadsheet\IOFactory;
use Yii;

class CatalogModelImportSpreadsheetReader
{
    public const SHEET_MODELS = 'Модели';

    public function __construct(
        private readonly CatalogModelSpreadsheetReader $modelsReader = new CatalogModelSpreadsheetReader(),
    ) {
    }

    /**
     * @return CatalogModelImportRowDto[]
     */
    public function read(string $filePath, int $startIndex = 0): array
    {
        $allRows = $this->loadAllRows($filePath);

        return $startIndex > 0 ? array_slice($allRows, $startIndex) : $allRows;
    }

    public function countRows(string $filePath): int
    {
        return count($this->loadAllRows($filePath));
    }

    /**
     * @return CatalogModelImportRowDto[]
     */
    private function loadAllRows(string $filePath): array
    {
        $cached = CatalogModelSpreadsheetRowCache::loadIfFresh($filePath);
        if ($cached !== null) {
            return $cached;
        }

        $parseStarted = microtime(true);
        $rows = $this->readViaPhpSpreadsheet($filePath);
        Yii::info(
            sprintf(
                '[MODEL_IMPORT] parse rows=%d ms=%d',
                count($rows),
                (int)round((microtime(true) - $parseStarted) * 1000)
            ),
            'catalog-model-import'
        );

        CatalogModelSpreadsheetRowCache::save($filePath, $rows);

        return $rows;
    }

    /**
     * @return CatalogModelImportRowDto[]
     */
    private function readViaPhpSpreadsheet(string $filePath): array
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $readerType = $extension === 'xls' ? 'Xls' : 'Xlsx';
        $reader = IOFactory::createReader($readerType);
        $reader->setReadDataOnly(true);
        $reader->setLoadSheetsOnly([self::SHEET_MODELS]);
        $reader->setReadFilter(new CatalogModelSheetReadFilter());

        $spreadsheet = $reader->load($filePath);
        $modelsSheet = $spreadsheet->getSheetByName(self::SHEET_MODELS);
        if ($modelsSheet === null) {
            throw new \InvalidArgumentException('В файле нет листа «' . self::SHEET_MODELS . '».');
        }

        if (!$this->modelsReader->isModelsSheet($modelsSheet)) {
            throw new \InvalidArgumentException('Лист «' . self::SHEET_MODELS . '» не соответствует шаблону импорта.');
        }

        $rows = $this->modelsReader->readSheet($modelsSheet);
        if ($rows === []) {
            throw new \InvalidArgumentException('На листе «' . self::SHEET_MODELS . '» нет строк для импорта.');
        }

        return $rows;
    }
}
