<?php

use app\services\media\FabricSwatchVariantRegenerator;
use yii\db\Migration;

/**
 * После импорта тканей из файла на диске могли остаться только оригиналы без mini/medium/large (webp).
 */
class m261016_200000_fabric_import_swatch_variants extends Migration
{
    public function safeUp(): void
    {
        $service = new FabricSwatchVariantRegenerator();
        $result = $service->regenerateMissing(false);

        echo sprintf(
            'Fabric swatch variants: processed=%d regenerated=%d skipped=%d failed=%d' . PHP_EOL,
            $result['processed'],
            $result['regenerated'],
            $result['skipped'],
            $result['failed']
        );

        foreach ($result['messages'] as $message) {
            echo '  ' . $message . PHP_EOL;
        }

        if (($result['failed'] ?? 0) > 0) {
            echo sprintf(
                'Warning: %d swatch(es) could not be regenerated (missing originals); migration continues.' . PHP_EOL,
                (int)$result['failed']
            );
        }
    }

    public function safeDown(): void
    {
        echo "m261016_200000_fabric_import_swatch_variants: откат не выполняется (генерация файлов)." . PHP_EOL;
    }
}
