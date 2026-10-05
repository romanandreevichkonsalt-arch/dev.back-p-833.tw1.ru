<?php

namespace app\services\import\catalog;

use PhpOffice\PhpSpreadsheet\IOFactory;

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
    public function read(string $filePath): array
    {
        $spreadsheet = IOFactory::load($filePath);
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
