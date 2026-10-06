<?php

namespace tests\unit\services\import;

use app\models\CatalogFabricCollection;
use app\models\CatalogFabricColor;
use app\services\import\fabric\FabricColorDescriptionImporter;
use app\services\import\fabric\FabricColorDescriptionRowDto;
use Codeception\Test\Unit;

class FabricColorDescriptionImporterTest extends Unit
{
    public function testImportUpdatesMatchingFabricColorDescription(): void
    {
        $fabric = new CatalogFabricCollection([
            'slug' => 'desc-import-fabric-' . uniqid('', true),
            'name' => 'STRONG',
            'sort_order' => 0,
            'is_active' => true,
        ]);
        verify($fabric->save(false))->true();

        $color = new CatalogFabricColor([
            'fabric_collection_id' => (int)$fabric->id,
            'design_code' => '0',
            'sort_order' => 0,
            'is_active' => true,
        ]);
        verify($color->save(false))->true();

        $importer = new FabricColorDescriptionImporter(new FakeFabricColorDescriptionReader([
            new FabricColorDescriptionRowDto(5, 'STRONG', '0', "Strong 0\n\nОписание"),
        ]));

        $result = $importer->importFromFile('/tmp/unused.xlsx', true);
        verify($result->updated)->equals(1);

        $color->refresh();
        verify($color->description)->equals("Strong 0\n\nОписание");
        verify($color->getDescriptionForApi())->equals("Strong 0\n\nОписание");
    }

    public function testImportSkipsExistingDescriptionWhenOverwriteDisabled(): void
    {
        $fabric = new CatalogFabricCollection([
            'slug' => 'desc-import-fabric-skip-' . uniqid('', true),
            'name' => 'GUCCI',
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $fabric->save(false);

        $color = new CatalogFabricColor([
            'fabric_collection_id' => (int)$fabric->id,
            'design_code' => '422',
            'description' => 'Старое описание',
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $color->save(false);

        $importer = new FabricColorDescriptionImporter(new FakeFabricColorDescriptionReader([
            new FabricColorDescriptionRowDto(7, 'GUCCI', '422', 'Новое описание'),
        ]));

        $result = $importer->importFromFile('/tmp/unused.xlsx', false);
        verify($result->skippedExisting)->equals(1);
        verify($result->updated)->equals(0);

        $color->refresh();
        verify($color->description)->equals('Старое описание');
    }
}

class FakeFabricColorDescriptionReader
{
    /**
     * @param FabricColorDescriptionRowDto[] $rows
     */
    public function __construct(private readonly array $rows)
    {
    }

    /**
     * @return FabricColorDescriptionRowDto[]
     */
    public function read(string $filePath): array
    {
        return $this->rows;
    }
}
