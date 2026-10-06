<?php

namespace app\services\import\fabric;

use app\models\CatalogFabricCollection;
use app\models\CatalogFabricColor;

class FabricColorDescriptionImporter
{
    public function __construct(
        private readonly object $reader = new FabricColorDescriptionSpreadsheetReader(),
    ) {
    }

    public function importFromFile(string $filePath, bool $overwriteExisting = true): FabricColorDescriptionImportResult
    {
        $result = new FabricColorDescriptionImportResult();
        $rows = $this->reader->read($filePath);
        $result->totalRows = count($rows);

        $collectionsByName = $this->indexFabricCollectionsByName();

        foreach ($rows as $row) {
            $collection = $this->resolveCollection($collectionsByName, $row->fabricCollectionName);
            if ($collection === null) {
                $result->notFound++;
                $result->addMessage(
                    'Строка ' . $row->rowNumber . ': коллекция ткани «' . $row->fabricCollectionName . '» не найдена.'
                );
                continue;
            }

            $color = CatalogFabricColor::find()
                ->where([
                    'fabric_collection_id' => (int)$collection->id,
                    'design_code' => $row->designCode,
                ])
                ->one();

            if ($color === null) {
                $result->notFound++;
                $result->addMessage(
                    'Строка ' . $row->rowNumber . ': цвет «'
                    . $row->fabricCollectionName . ' / ' . $row->designCode . '» не найден.'
                );
                continue;
            }

            $current = trim((string)($color->description ?? ''));
            if (!$overwriteExisting && $current !== '') {
                $result->skippedExisting++;
                continue;
            }

            if ($current === $row->description) {
                $result->unchanged++;
                continue;
            }

            $color->description = $row->description;
            $color->save(false, ['description', 'updated_at']);
            $result->updated++;
        }

        return $result;
    }

    /**
     * @return array<string, CatalogFabricCollection>
     */
    private function indexFabricCollectionsByName(): array
    {
        $indexed = [];
        foreach (CatalogFabricCollection::find()->all() as $collection) {
            if (!$collection instanceof CatalogFabricCollection) {
                continue;
            }
            $key = $this->normalizeCollectionKey((string)$collection->name);
            if ($key === '') {
                continue;
            }
            $indexed[$key] = $collection;
        }

        return $indexed;
    }

    /**
     * @param array<string, CatalogFabricCollection> $collectionsByName
     */
    private function resolveCollection(array $collectionsByName, string $name): ?CatalogFabricCollection
    {
        $key = $this->normalizeCollectionKey($name);
        if ($key === '') {
            return null;
        }

        return $collectionsByName[$key] ?? null;
    }

    private function normalizeCollectionKey(string $name): string
    {
        return mb_strtolower(trim($name));
    }
}
