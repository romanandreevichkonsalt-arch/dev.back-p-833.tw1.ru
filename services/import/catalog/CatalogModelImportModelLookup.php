<?php

namespace app\services\import\catalog;

use app\models\CatalogModel;

/**
 * Prefetch моделей по (collection_id, subcategory_id) на время импорта прайса.
 */
final class CatalogModelImportModelLookup
{
    /** @var array<string, CatalogModel> */
    private array $byKey = [];

    /**
     * @param CatalogModelImportRowDto[] $rows
     */
    public static function warm(
        array $rows,
        CatalogImportReferenceResolver $resolver,
        CatalogModelImportOptions $options,
    ): self {
        $lookup = new self();
        if ($options->dryRun || $rows === []) {
            return $lookup;
        }

        /** @var array<string, array{collection_id: int, subcategory_id: int}> $pairs */
        $pairs = [];
        foreach ($rows as $row) {
            try {
                $direction = $resolver->resolveDirection($row->directionLabel);
                $collection = $resolver->resolveCollection((int)$direction->id, $row->collectionName);
                $category = $resolver->resolveCategory($row->categoryLabel);
                $subcategory = $resolver->resolveSubcategory($category, $row->subcategoryLabel);
            } catch (\Throwable) {
                continue;
            }

            $collectionId = (int)$collection->id;
            $subcategoryId = (int)$subcategory->id;
            $key = self::key($collectionId, $subcategoryId);
            $pairs[$key] = [
                'collection_id' => $collectionId,
                'subcategory_id' => $subcategoryId,
            ];
        }

        if ($pairs === []) {
            return $lookup;
        }

        $pairList = array_values($pairs);
        foreach (array_chunk($pairList, 80) as $chunk) {
            $conditions = ['or'];
            foreach ($chunk as $pair) {
                $conditions[] = [
                    'collection_id' => $pair['collection_id'],
                    'subcategory_id' => $pair['subcategory_id'],
                ];
            }

            $models = CatalogModel::find()->where($conditions)->all();
            foreach ($models as $model) {
                $lookup->remember($model);
            }
        }

        return $lookup;
    }

    public function find(int $collectionId, int $subcategoryId): ?CatalogModel
    {
        return $this->byKey[self::key($collectionId, $subcategoryId)] ?? null;
    }

    public function remember(CatalogModel $model): void
    {
        if ($model->isNewRecord) {
            return;
        }

        $this->byKey[self::key((int)$model->collection_id, (int)$model->subcategory_id)] = $model;
    }

    private static function key(int $collectionId, int $subcategoryId): string
    {
        return $collectionId . ':' . $subcategoryId;
    }
}
