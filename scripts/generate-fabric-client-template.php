<?php

declare(strict_types=1);

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

require __DIR__ . '/../vendor/autoload.php';

$spreadsheet = new Spreadsheet();
$spreadsheet->getProperties()
    ->setTitle('Реестр тканей — шаблон')
    ->setSubject('Заполнение коллекций и цветов')
    ->setDescription('Шаблон для передачи данных о тканях');

$instructionSheet = $spreadsheet->getActiveSheet();
$instructionSheet->setTitle('Как заполнять');

$instructions = [
    ['Реестр тканей — инструкция для заполнения'],
    [''],
    ['Структура файла'],
    ['• Лист «Коллекции» — одна строка = одна коллекция (GUCCI, Leonardo и т.д.).'],
    ['• Лист «Цвета» — одна строка = один цвет в коллекции.'],
    ['• Название коллекции в обоих листах должно совпадать точно.'],
    [''],
    ['Обязательные поля отмечены * в заголовках.'],
    [''],
    ['Коллекции'],
    ['• Категория — «Ткань» или «Кожа».'],
    ['• Тип фактуры — букле, шенилл, велюр и т.п. (общее для всей коллекции).'],
    ['• Описание, состав, martindale, свойства — заполняются один раз на коллекцию.'],
    ['• Ценовые категории — необязательно; диапазоны цен можно уточнить у заказчика отдельно.'],
    [''],
    ['Цвета'],
    ['• Цветодизайн — код/название цвета внутри коллекции (422, 690, 4.0, Savana Terracotta).'],
    ['• Цвет — группа из справочника (Серый, Бежевый, Зеленый…), не путать с цветодизайном.'],
    ['• Ссылка на фото — прямая ссылка на файл изображения или страница товара на souz-m.ru.'],
    ['  Не подходят ссылки на папки Google Drive / Яндекс.Диск — только на конкретный файл.'],
    ['• Комментарий — внутренняя заметка, необязательно.'],
    [''],
    ['Примеры заполнения — на листах «Коллекции» и «Цвета» (строки 3–5). Их можно удалить перед отправкой.'],
];

$row = 1;
foreach ($instructions as $line) {
    $instructionSheet->setCellValue('A' . $row, $line[0] ?? '');
    $row++;
}
$instructionSheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$instructionSheet->getColumnDimension('A')->setWidth(110);
$instructionSheet->getStyle('A3:A' . ($row - 1))->getAlignment()->setWrapText(true);

$collectionsSheet = $spreadsheet->createSheet();
$collectionsSheet->setTitle('Коллекции');

$collectionHeaders = [
    'Категория *',
    'Коллекция *',
    'Состав',
    'Тип фактуры',
    'Martindale',
    'Свойства / уход',
    'Ширина рулона, см',
    'Плотность, г/м²',
    'Описание',
    'Ценовая категория А+',
    'Ценовая категория Линия 1',
];

$collectionExamples = [
    [
        'Ткань',
        'GUCCI',
        'полиэстер - 100%',
        'Букле',
        '60000',
        'беречь от прямых солнечных лучей; чистка мылом',
        '142',
        '635',
        'Выверенный шарм, выраженная фактура, богатая цветовая палитра.',
        '5 кат (от 1001 до 1100 руб)',
        '3 кат (от 801 до 900 руб)',
    ],
    [
        'Ткань',
        'Leonardo',
        'полиэстер - 100%',
        'Рогожка',
        '40000',
        'не использовать абразивные средства',
        '140',
        '420',
        'Коллекция с натуральной фактурой для мягкой мебели.',
        '',
        '',
    ],
    [
        'Ткань',
        'Mistral',
        'полиэстер - 100%',
        'Велюр',
        '50000',
        '',
        '142',
        '380',
        '',
        '',
        '',
    ],
];

writeHeaderRow($collectionsSheet, 1, $collectionHeaders);
writeDataRows($collectionsSheet, 2, $collectionExamples);
autoSizeColumns($collectionsSheet, count($collectionHeaders));

$colorsSheet = $spreadsheet->createSheet();
$colorsSheet->setTitle('Цвета');

$colorHeaders = [
    'Коллекция *',
    'Цветодизайн *',
    'Цвет *',
    'Ссылка на фото',
    'Комментарий',
];

$colorExamples = [
    [
        'GUCCI',
        '422',
        'Бежевый',
        'https://cloud.mail.ru/public/…/GUCCI%20422%20(32.32).jpg',
        '',
    ],
    [
        'GUCCI',
        '690',
        'Зеленый',
        'https://souz-m.ru/products/gucci-690.html',
        '',
    ],
    [
        'GUCCI',
        '100',
        'Белый',
        'https://souz-m.ru/products/gucci-100.html',
        '',
    ],
    [
        'Leonardo',
        '4',
        'Серый',
        'https://disk.yandex.ru/d/…/LEONARDO%20(цвет%204)-2.jpg',
        'Прямая ссылка на файл, не на папку',
    ],
    [
        'Leonardo',
        '1',
        'Бежевый',
        'https://disk.yandex.ru/d/…/LEONARDO%20(цвет%201)-2.jpg',
        '',
    ],
    [
        'Mistral',
        '10',
        'Терракота',
        'https://drive.google.com/file/d/…/view',
        'Ссылка на файл, не на папку Drive',
    ],
];

writeHeaderRow($colorsSheet, 1, $colorHeaders);
writeDataRows($colorsSheet, 2, $colorExamples);
autoSizeColumns($colorsSheet, count($colorHeaders));

$outputDir = __DIR__ . '/../docs/examples';
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0775, true);
}

$outputPath = $outputDir . '/Реестр_тканей_шаблон_для_клиента.xlsx';
$writer = new Xlsx($spreadsheet);
$writer->save($outputPath);

echo "Created: {$outputPath}\n";

function writeHeaderRow(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $row, array $headers): void
{
    $col = 'A';
    foreach ($headers as $header) {
        $sheet->setCellValue($col . $row, $header);
        $col++;
    }

    $lastCol = chr(ord('A') + count($headers) - 1);
    $range = 'A' . $row . ':' . $lastCol . $row;
    $sheet->getStyle($range)->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => '2F4F6F'],
        ],
        'alignment' => [
            'vertical' => Alignment::VERTICAL_CENTER,
            'wrapText' => true,
        ],
        'borders' => [
            'allBorders' => ['borderStyle' => Border::BORDER_THIN],
        ],
    ]);
    $sheet->getRowDimension($row)->setRowHeight(28);
}

function writeDataRows(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $startRow, array $rows): void
{
    $rowIndex = $startRow;
    foreach ($rows as $row) {
        $col = 'A';
        foreach ($row as $value) {
            $sheet->setCellValue($col . $rowIndex, $value);
            $col++;
        }
        $rowIndex++;
    }

    if ($rows === []) {
        return;
    }

    $colCount = count($rows[0]);
    $lastCol = chr(ord('A') + $colCount - 1);
    $range = 'A' . $startRow . ':' . $lastCol . ($rowIndex - 1);
    $sheet->getStyle($range)->applyFromArray([
        'alignment' => ['wrapText' => true, 'vertical' => Alignment::VERTICAL_TOP],
        'borders' => [
            'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'DDDDDD']],
        ],
    ]);
}

function autoSizeColumns(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $count): void
{
    for ($i = 0; $i < $count; $i++) {
        $col = chr(ord('A') + $i);
        $width = match ($i) {
            0 => 14,
            1 => 18,
            2, 5, 8 => 36,
            3 => 16,
            default => 22,
        };
        $sheet->getColumnDimension($col)->setWidth($width);
    }
}
