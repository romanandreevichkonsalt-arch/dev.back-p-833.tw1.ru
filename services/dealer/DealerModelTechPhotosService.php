<?php

namespace app\services\dealer;

use app\models\CatalogCollection;
use app\models\CatalogModel;

final class DealerModelTechPhotosService
{
    /**
     * По одной ссылке на диск с тех. фото на коллекцию мебели (из поля модели в админке).
     *
     * @return array{
     *     url: ?string,
     *     items: list<array{label: string, url: string}>,
     *     meta: array{total: int}
     * }
     */
    public function listFolderLinks(): array
    {
        $rows = CatalogModel::find()
            ->alias('m')
            ->select([
                'collection_id' => 'm.collection_id',
                'folder_url' => 'm.tech_photos_folder_url',
            ])
            ->innerJoin(
                ['c' => CatalogCollection::tableName()],
                'c.id = m.collection_id AND c.is_active = 1'
            )
            ->where(['m.is_active' => true])
            ->andWhere(['not', ['m.tech_photos_folder_url' => null]])
            ->andWhere(['<>', 'm.tech_photos_folder_url', ''])
            ->orderBy(['m.collection_id' => SORT_ASC, 'm.sort_order' => SORT_ASC, 'm.id' => SORT_ASC])
            ->asArray()
            ->all();

        $collectionIds = [];
        foreach ($rows as $row) {
            $collectionIds[(int)$row['collection_id']] = (int)$row['collection_id'];
        }

        /** @var array<int, CatalogCollection> $collections */
        $collections = $collectionIds === []
            ? []
            : CatalogCollection::find()
                ->where(['id' => array_values($collectionIds)])
                ->indexBy('id')
                ->all();

        $items = [];
        $seenCollections = [];
        foreach ($rows as $row) {
            $collectionId = (int)$row['collection_id'];
            if (isset($seenCollections[$collectionId])) {
                continue;
            }

            $folderUrl = trim((string)$row['folder_url']);
            if ($folderUrl === '') {
                continue;
            }

            $collection = $collections[$collectionId] ?? null;
            $label = $collection !== null
                ? trim((string)$collection->getDisplayName())
                : '';

            if ($label === '') {
                continue;
            }

            $seenCollections[$collectionId] = true;
            $items[] = [
                'label' => $label,
                'url' => $folderUrl,
            ];
        }

        usort(
            $items,
            static fn (array $a, array $b): int => strcasecmp($a['label'], $b['label'])
        );

        $distinctUrls = array_values(array_unique(array_column($items, 'url')));
        $commonUrl = count($distinctUrls) === 1 ? $distinctUrls[0] : null;

        return [
            'url' => $commonUrl,
            'items' => $items,
            'meta' => [
                'total' => count($items),
            ],
        ];
    }
}
