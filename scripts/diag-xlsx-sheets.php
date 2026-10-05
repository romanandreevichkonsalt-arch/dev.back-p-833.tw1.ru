<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$path = $argv[1] ?? '';
if ($path === '' || !is_file($path)) {
    fwrite(STDERR, "Usage: php scripts/diag-xlsx-sheets.php <file.xlsx>\n");
    exit(1);
}

$ss = IOFactory::load($path);
echo 'Sheets:' . PHP_EOL;
foreach ($ss->getSheetNames() as $name) {
    echo '  - ' . json_encode($name, JSON_UNESCAPED_UNICODE) . PHP_EOL;
    echo '    codepoints: ' . implode(' ', array_map(
        static fn (string $c): string => sprintf('U+%04X', mb_ord($c)),
        preg_split('//u', $name, -1, PREG_SPLIT_NO_EMPTY) ?: []
    )) . PHP_EOL;
}

$target = 'Ткани и кожа';
$sheet = $ss->getSheetByName($target);
echo 'getSheetByName(' . json_encode($target, JSON_UNESCAPED_UNICODE) . '): '
    . ($sheet !== null ? 'found' : 'NOT FOUND') . PHP_EOL;
