<?php

namespace tests\unit\services\import;

use app\services\import\surface\SurfaceMaterialRegistrySpreadsheetReader;
use Codeception\Test\Unit;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class SurfaceMaterialRegistrySpreadsheetReaderTest extends Unit
{
    public function testReadsWoodMetalSheet(): void
    {
        $path = $this->createFixture();
        $rows = (new SurfaceMaterialRegistrySpreadsheetReader())->read($path);

        verify($rows)->arrayCount(1);
        verify($rows[0]->materialType)->equals('Дерево');
        verify($rows[0]->name)->equals('Дуб натуральный');
        verify($rows[0]->appliedModelsText)->equals('Артемида');
        verify($rows[0]->photoUrl)->stringContainsString('oak.jpg');
        verify($rows[0]->description)->equals('Описание материала');

        @unlink($path);
    }

    private function createFixture(): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(SurfaceMaterialRegistrySpreadsheetReader::SHEET);
        $sheet->setCellValue('A3', '№');
        $sheet->setCellValue('B3', 'Тип');
        $sheet->setCellValue('C3', 'Название / тонировка');
        $sheet->setCellValue('D3', 'Применяется на модели');
        $sheet->setCellValue('E3', 'Ссылка на ФОТО');
        $sheet->setCellValue('F3', 'Ссылка на ТЕКСТУРУ');
        $sheet->setCellValue('G3', 'Описание');
        $sheet->setCellValue('H3', 'Комментарий');

        $sheet->setCellValue('A4', 1);
        $sheet->setCellValue('B4', 'Дерево');
        $sheet->setCellValue('C4', 'Дуб натуральный');
        $sheet->setCellValue('D4', 'Артемида');
        $sheet->setCellValue('E4', 'https://example.test/oak.jpg');
        $sheet->setCellValue('F4', 'https://example.test/oak.zip');
        $sheet->setCellValue('G4', 'Описание материала');
        $sheet->setCellValue('H4', 'не импортируем');

        $path = codecept_output_dir() . 'surface-material-registry.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }
}
