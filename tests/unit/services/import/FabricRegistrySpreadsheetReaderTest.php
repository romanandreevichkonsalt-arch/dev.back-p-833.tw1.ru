<?php

namespace tests\unit\services\import;

use app\services\import\fabric\FabricRegistrySpreadsheetReader;
use app\services\import\SpreadsheetFormatValidator;
use app\services\import\SpreadsheetSheetResolver;
use Codeception\Test\Unit;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class FabricRegistrySpreadsheetReaderTest extends Unit
{
    private string $clientFile;

    protected function _before(): void
    {
        $this->clientFile = dirname(__DIR__, 4) . '/docs/examples/Реестр_тканей_шаблон_для_клиента.xlsx';
        if (!is_file($this->clientFile)) {
            exec('php ' . escapeshellarg(dirname(__DIR__, 4) . '/scripts/generate-fabric-client-template.php'));
        }
    }

    public function testReadsClientTemplate(): void
    {
        if (!is_file($this->clientFile)) {
            $this->markTestSkipped('Client template file is unavailable.');
        }

        $rows = (new FabricRegistrySpreadsheetReader())->read($this->clientFile);
        verify($rows)->notEmpty();
        verify($rows[0]->collectionName)->equals('GUCCI');
        verify($rows[0]->colorName)->equals('422');
        verify($rows[0]->texture)->equals('Букле');
    }

    public function testSheetResolverFindsClientSheets(): void
    {
        if (!is_file($this->clientFile)) {
            $this->markTestSkipped('Client template file is unavailable.');
        }

        $spreadsheet = IOFactory::load($this->clientFile);
        verify(SpreadsheetSheetResolver::findSheet($spreadsheet, ['Коллекции']))->notNull();
        verify(SpreadsheetSheetResolver::findSheet($spreadsheet, ['Цвета']))->notNull();
    }

    public function testReadsRegistryV2RecommendedAndPositionColumns(): void
    {
        $path = codecept_output_dir() . 'fabric-registry-v2-recommended.xlsx';
        $workbook = new Spreadsheet();
        $registrySheet = $workbook->getActiveSheet();
        $registrySheet->setTitle(FabricRegistrySpreadsheetReader::SHEET_FABRICS);
        $registrySheet->fromArray([
            [1, 'Ткань', 'Demo', '422', null, null, null, 'Букле', 'Терракота', null, null, null, null, null, 'да', '7', 'Описание', 'Коммент'],
        ], null, 'A' . FabricRegistrySpreadsheetReader::DATA_START_ROW);
        (new Xlsx($workbook))->save($path);

        try {
            $rows = (new FabricRegistrySpreadsheetReader())->read($path);
            verify(count($rows))->equals(1);
            verify($rows[0]->collectionName)->equals('Demo');
            verify($rows[0]->isRecommendedFabric)->true();
            verify($rows[0]->positionNumber)->equals(7);
            verify($rows[0]->description)->equals('Описание');
            verify($rows[0]->importComment)->equals('Коммент');
        } finally {
            @unlink($path);
        }
    }

    public function testRejectsHtmlDisguisedAsExcel(): void
    {
        $path = codecept_output_dir() . 'fake-import.xlsx';
        file_put_contents($path, '<html><body>login</body></html>');

        try {
            $this->expectException(\InvalidArgumentException::class);
            SpreadsheetFormatValidator::assertReadableExcel($path);
        } finally {
            @unlink($path);
        }
    }
}
