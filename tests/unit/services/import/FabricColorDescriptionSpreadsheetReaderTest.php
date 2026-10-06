<?php

namespace tests\unit\services\import;

use app\services\import\fabric\FabricColorDescriptionSpreadsheetReader;
use Codeception\Test\Unit;

class FabricColorDescriptionSpreadsheetReaderTest extends Unit
{
    public function testReadsClientColorDesignFileWhenPresent(): void
    {
        $filePath = $this->resolveSampleFilePath();
        if ($filePath === null) {
            $this->markTestSkipped('Sample xlsx not available in this environment.');
        }

        $rows = (new FabricColorDescriptionSpreadsheetReader())->read($filePath);
        verify($rows)->notEmpty();
        verify($rows[0]->fabricCollectionName)->equals('STRONG');
        verify($rows[0]->designCode)->equals('0');
        verify($rows[0]->description)->stringContainsString('Strong 0');
    }

    private function resolveSampleFilePath(): ?string
    {
        $directory = '/home/vi/Загрузки/Telegram Desktop';
        if (!is_dir($directory)) {
            return null;
        }

        foreach (scandir($directory) ?: [] as $name) {
            if (str_contains($name, 'итог_3') && str_ends_with($name, '.xlsx')) {
                $path = $directory . '/' . $name;

                return is_file($path) ? $path : null;
            }
        }

        return null;
    }
}
