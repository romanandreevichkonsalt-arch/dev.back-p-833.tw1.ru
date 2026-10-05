<?php

declare(strict_types=1);

$sourcePath = __DIR__ . '/../docs/examples/Прайс_моделей_шаблон_для_клиента.xlsx';
$outputPath = $sourcePath;

if (!is_file($sourcePath)) {
    fwrite(STDERR, "Template file not found: {$sourcePath}\n");
    fwrite(STDERR, "Place the client template at docs/examples/Прайс_моделей_шаблон_для_клиента.xlsx\n");
    exit(1);
}

echo "Template is maintained at: {$outputPath}\n";
