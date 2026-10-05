<?php

namespace app\services\catalog;

use app\models\CatalogBadge;

/**
 * Подготовка ключей round-robin для разных sort в листинге.
 */
class CatalogListingSort
{
    public const LINE1_DIRECTION_SLUG = 'line-1';

    /**
     * Только sort=alphabet в каталоге. default/popular — RR по model_id (см. buildRoundRobinRow).
     * Поиск использует buildCollectionPriceRoundRobinRow через SearchProductOrderingService.
     */
    public function useCollectionPriceRoundRobin(string $sort): bool
    {
        return $sort === 'alphabet';
    }

    /**
     * Round-robin по линейкам мебели (A→Z) × цена ↑ — режим alphabet и поиск.
     *
     * @param array<string, mixed> $row
     */
    public function buildCollectionPriceRoundRobinRow(array $row, string $directionSlug): array
    {
        $collectionId = (int)($row['collection_id'] ?? 0);
        $id = (int)$row['id'];
        $groupKey = $collectionId > 0 ? $collectionId : (-1 * $id);

        $base = $this->buildRoundRobinRow($row, 'price_asc');
        $base['groupKey'] = $groupKey;
        $base['collectionKey'] = $groupKey;

        $displayName = trim((string)($row['collection_display_name'] ?? ''));
        if ($displayName === '') {
            $displayName = trim((string)($row['collection_title'] ?? ''));
        }

        $directionRank = $directionSlug === self::LINE1_DIRECTION_SLUG ? 0 : 1;
        $base['groupSort'] = sprintf(
            '%01d-%s-%010d',
            $directionRank,
            mb_strtolower($displayName),
            max(0, $collectionId)
        );

        return $base;
    }

    public function buildRoundRobinRow(array $row, string $sort): array
    {
        $id = (int)$row['id'];
        $modelId = (int)($row['model_id'] ?? 0);
        $collectionId = (int)($row['collection_id'] ?? 0);

        return [
            'id' => $id,
            'groupKey' => $modelId > 0 ? $modelId : -1 * $id,
            'collectionKey' => $collectionId > 0 ? $collectionId : -1 * $id,
            'groupSort' => sprintf(
                '%010d-%010d-%010d',
                (int)($row['collection_sort'] ?? 0),
                (int)($row['model_sort'] ?? 0),
                $modelId
            ),
            'intraSort' => $this->buildIntraSort($sort, $row, $id),
        ];
    }

    /**
     * Ключ сортировки для round-robin в листинге каталога.
     * sort=popular — тот же порядок, что default (цена ↑, смешение моделей); «популярность» SKU — только флаг isPopular в карточке.
     */
    public function effectiveRoundRobinSort(string $sort): string
    {
        return in_array($sort, ['default', 'popular'], true) ? 'default' : $sort;
    }

    public function orderGroupsByBucketHead(string $sort): bool
    {
        return !in_array($sort, ['default', 'popular'], true);
    }

    /**
     * @param array<string, mixed> $document
     */
    public function buildRoundRobinRowFromSearchDocument(array $document, string $sort, int $index): array
    {
        $id = (string)($document['id'] ?? $document['slug'] ?? ('idx-' . $index));

        return [
            'id' => $id,
            'groupKey' => $document['_groupKey'] ?? ('idx-' . $index),
            'collectionKey' => $document['_collectionKey'] ?? ('idx-' . $index),
            'groupSort' => $document['_groupSort'] ?? sprintf('%010d', $index),
            'intraSort' => $this->buildIntraSortFromSearchDocument($sort, $document, $index),
        ];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function buildIntraSort(string $sort, array $row, int $id): string
    {
        return match ($sort) {
            'price_asc' => sprintf(
                '%02d-%011d-%010d-%010d-%010d',
                (int)($row['price_amount'] === null),
                (int)($row['price_amount'] ?? PHP_INT_MAX),
                (int)($row['fabric_sort'] ?? 0),
                (int)($row['color_sort'] ?? 0),
                $id
            ),
            'price_desc' => sprintf(
                '%02d-%011d-%010d-%010d-%010d',
                (int)($row['price_amount'] === null),
                (int)(PHP_INT_MAX - (int)($row['price_amount'] ?? 0)),
                (int)($row['fabric_sort'] ?? 0),
                (int)($row['color_sort'] ?? 0),
                $id
            ),
            'popular' => sprintf(
                '%01d-%01d-%010d-%010d',
                (int)!((bool)($row['is_popular'] ?? false)),
                (int)(($row['badge_variant'] ?? '') !== CatalogBadge::VARIANT_HIT),
                (int)($row['sort_order'] ?? 0),
                $id
            ),
            'new' => sprintf(
                '%01d-%010d-%010d-%010d',
                (int)(($row['badge_variant'] ?? '') !== CatalogBadge::VARIANT_NEW),
                $this->createdAtSortKey($row['created_at'] ?? null),
                (int)($row['sort_order'] ?? 0),
                $id
            ),
            'default' => sprintf(
                '%02d-%011d-%010d-%010d-%010d',
                (int)($row['price_amount'] === null),
                (int)($row['price_amount'] ?? PHP_INT_MAX),
                (int)($row['fabric_sort'] ?? 0),
                (int)($row['color_sort'] ?? 0),
                $id
            ),
            default => sprintf(
                '%010d-%010d-%010d',
                (int)($row['fabric_sort'] ?? 0),
                (int)($row['color_sort'] ?? 0),
                $id
            ),
        };
    }

    /**
     * @param array<string, mixed> $document
     */
    private function buildIntraSortFromSearchDocument(string $sort, array $document, int $index): string
    {
        $row = [
            'price_amount' => $document['_priceAmount'] ?? null,
            'is_popular' => $document['_isPopular'] ?? false,
            'badge_variant' => $document['_badgeVariant'] ?? null,
            'created_at' => $document['_createdAt'] ?? null,
            'sort_order' => $document['_sortOrder'] ?? 0,
            'fabric_sort' => $document['_fabricSort'] ?? 0,
            'color_sort' => $document['_colorSort'] ?? 0,
        ];

        if ($sort === 'default') {
            return (string)($document['_intraSort'] ?? $this->buildIntraSort('default', $row, $index));
        }

        return $this->buildIntraSort($sort, $row, $index);
    }

    private function createdAtSortKey(?string $createdAt): int
    {
        if ($createdAt === null || trim($createdAt) === '') {
            return PHP_INT_MAX;
        }

        $timestamp = strtotime($createdAt);
        if ($timestamp === false) {
            return PHP_INT_MAX;
        }

        return PHP_INT_MAX - $timestamp;
    }
}
