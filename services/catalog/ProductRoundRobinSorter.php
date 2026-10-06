<?php

namespace app\services\catalog;

/**
 * Чередование SKU: по одному товару из каждой модели за круг,
 * внутри модели — по intraSort (ткань→цвет для default, цена/популярность и т.д. для других sort).
 *
 * Сдвиг по позиции модели: группа на индексе p начинает с p-го элемента bucket,
 * чтобы на первом круге не показывать у всех моделей один и тот же оттенок.
 *
 * collectionKey: старается не ставить подряд SKU одной коллекции, если есть альтернатива в том же круге.
 */
class ProductRoundRobinSorter
{
    /**
     * @param list<array{id: int|string, groupKey: int|string, groupSort: int|string, intraSort: int|string, collectionKey?: int|string}> $rows
     * @return list<int|string>
     */
    public function sortIds(
        array $rows,
        bool $orderGroupsByBucketHead = false,
        array $priorityGroupKeys = [],
        bool $rotateIntraBuckets = true,
        ?int $maxResults = null,
    ): array
    {
        if ($rows === []) {
            return [];
        }

        $maxResults = $maxResults !== null ? max(0, $maxResults) : null;
        if ($maxResults === 0) {
            return [];
        }

        usort(
            $rows,
            static function (array $left, array $right): int {
                return [$left['groupSort'], $left['intraSort'], (string)$left['id']]
                    <=> [$right['groupSort'], $right['intraSort'], (string)$right['id']];
            }
        );

        /** @var array<string, list<array{id: int|string, groupKey: int|string, groupSort: int|string, intraSort: int|string, collectionKey?: int|string}>> $buckets */
        $buckets = [];
        $groupOrder = [];
        foreach ($rows as $row) {
            $key = (string)$row['groupKey'];
            if (!isset($buckets[$key])) {
                $buckets[$key] = [];
                $groupOrder[] = $key;
            }
            $buckets[$key][] = $row;
        }

        $groupOrder = $this->applyPriorityGroupOrder($groupOrder, $priorityGroupKeys);

        if ($orderGroupsByBucketHead) {
            usort(
                $groupOrder,
                static function (string $left, string $right) use ($buckets): int {
                    return strcmp(
                        (string)($buckets[$left][0]['intraSort'] ?? ''),
                        (string)($buckets[$right][0]['intraSort'] ?? '')
                    );
                }
            );
        }

        if ($rotateIntraBuckets) {
            foreach ($groupOrder as $position => $key) {
                if (!isset($buckets[$key])) {
                    continue;
                }
                $bucket = $buckets[$key];
                $count = count($bucket);
                if ($count < 2) {
                    continue;
                }
                $offset = $position % $count;
                if ($offset === 0) {
                    continue;
                }
                $buckets[$key] = array_merge(
                    array_slice($bucket, $offset),
                    array_slice($bucket, 0, $offset)
                );
            }
        }

        $ordered = [];
        $lastCollectionKey = null;
        while ($buckets !== []) {
            $emitted = false;
            $deferredKeys = [];

            foreach ($groupOrder as $key) {
                if (!isset($buckets[$key]) || $buckets[$key] === []) {
                    continue;
                }

                $head = $buckets[$key][0];
                $collectionKey = $this->collectionKey($head);

                if (
                    $lastCollectionKey !== null
                    && $collectionKey !== null
                    && $collectionKey === $lastCollectionKey
                    && $this->hasAlternativeCollection($buckets, $groupOrder, $deferredKeys, $lastCollectionKey)
                ) {
                    $deferredKeys[] = $key;
                    continue;
                }

                $picked = array_shift($buckets[$key]);
                $ordered[] = $picked['id'];
                $lastCollectionKey = $collectionKey;
                $emitted = true;
                if ($buckets[$key] === []) {
                    unset($buckets[$key]);
                }
                if ($maxResults !== null && count($ordered) >= $maxResults) {
                    return $ordered;
                }
            }

            foreach ($deferredKeys as $key) {
                if (!isset($buckets[$key]) || $buckets[$key] === []) {
                    continue;
                }

                $picked = array_shift($buckets[$key]);
                $ordered[] = $picked['id'];
                $lastCollectionKey = $this->collectionKey($picked);
                $emitted = true;
                if ($buckets[$key] === []) {
                    unset($buckets[$key]);
                }
                if ($maxResults !== null && count($ordered) >= $maxResults) {
                    return $ordered;
                }
            }

            if (!$emitted) {
                break;
            }
        }

        return $ordered;
    }

    /**
     * @param list<array<string, mixed>> $documents
     * @return list<array<string, mixed>>
     */
    public function sortDocuments(array $documents, bool $orderGroupsByBucketHead = false): array
    {
        if ($documents === []) {
            return [];
        }

        $rows = [];
        foreach ($documents as $index => $document) {
            $rows[] = [
                'id' => $index,
                'groupKey' => $document['_groupKey'] ?? ('idx-' . $index),
                'collectionKey' => $document['_collectionKey'] ?? ('idx-' . $index),
                'groupSort' => $document['_groupSort'] ?? sprintf('%010d', $index),
                'intraSort' => $document['_intraSort'] ?? sprintf('%010d', $index),
            ];
        }

        $ordered = [];
        foreach ($this->sortIds($rows, $orderGroupsByBucketHead) as $index) {
            $ordered[] = $documents[(int)$index];
        }

        return $ordered;
    }

    /**
     * @param list<array<string, mixed>> $documents
     * @return list<array<string, mixed>>
     */
    public function sortSearchDocuments(array $documents, string $sort): array
    {
        if ($documents === []) {
            return [];
        }

        $listingSort = new CatalogListingSort();
        $rows = [];
        foreach ($documents as $index => $document) {
            $rows[] = $listingSort->buildRoundRobinRowFromSearchDocument($document, $sort, $index);
        }

        $byKey = [];
        foreach ($documents as $index => $document) {
            $row = $rows[$index];
            $byKey[(string)$row['id']] = $document;
        }

        $ordered = [];
        foreach ($this->sortIds($rows, $listingSort->orderGroupsByBucketHead($sort)) as $id) {
            if (isset($byKey[(string)$id])) {
                $ordered[] = $byKey[(string)$id];
            }
        }

        return $ordered;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function collectionKey(array $row): ?string
    {
        if (!isset($row['collectionKey'])) {
            return null;
        }

        return (string)$row['collectionKey'];
    }

    /**
     * @param list<string> $groupOrder
     * @param list<int|string> $priorityGroupKeys
     * @return list<string>
     */
    private function applyPriorityGroupOrder(array $groupOrder, array $priorityGroupKeys): array
    {
        if ($priorityGroupKeys === []) {
            return $groupOrder;
        }

        $prioritySet = [];
        $ordered = [];
        foreach ($priorityGroupKeys as $key) {
            $key = (string)$key;
            if ($key === '' || isset($prioritySet[$key])) {
                continue;
            }
            $prioritySet[$key] = true;
            if (in_array($key, $groupOrder, true)) {
                $ordered[] = $key;
            }
        }

        foreach ($groupOrder as $key) {
            if (!isset($prioritySet[$key])) {
                $ordered[] = $key;
            }
        }

        return $ordered;
    }

    /**
     * @param array<string, list<array<string, mixed>>> $buckets
     * @param list<string> $groupOrder
     * @param list<string> $skipKeys
     */
    private function hasAlternativeCollection(
        array $buckets,
        array $groupOrder,
        array $skipKeys,
        string $avoidCollection
    ): bool {
        $skip = array_flip($skipKeys);

        foreach ($groupOrder as $key) {
            if (isset($skip[$key]) || !isset($buckets[$key]) || $buckets[$key] === []) {
                continue;
            }

            $collectionKey = $this->collectionKey($buckets[$key][0]);
            if ($collectionKey !== null && $collectionKey !== $avoidCollection) {
                return true;
            }
        }

        return false;
    }
}
