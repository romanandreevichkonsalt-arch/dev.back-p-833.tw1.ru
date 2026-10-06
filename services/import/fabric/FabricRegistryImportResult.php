<?php

namespace app\services\import\fabric;

use app\models\CatalogImportRun;

class FabricRegistryImportResult
{
    public bool $success = true;
    public bool $aborted = false;
    public ?CatalogImportRun $importRun = null;
    /** @var FabricRegistryImportRowResult[] */
    public array $rows = [];
    /** @var array<string, int> */
    public array $stats = [
        'rows_total' => 0,
        'rows_skipped' => 0,
        'collections_created' => 0,
        'collections_updated' => 0,
        'links_created' => 0,
        'links_updated' => 0,
        'links_conflict' => 0,
        'colors_created' => 0,
        'price_categories_created' => 0,
        'photo_imported' => 0,
        'photo_skipped' => 0,
        'errors' => 0,
    ];
    /** @var array<string, mixed>|null */
    public ?array $pendingConflict = null;

    public function addRow(FabricRegistryImportRowResult $row): void
    {
        $this->rows[] = $row;
    }

    public function hasConflicts(): bool
    {
        return $this->pendingConflict !== null
            || ($this->stats['links_conflict'] ?? 0) > 0;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'aborted' => $this->aborted,
            'stats' => $this->stats,
            'pending_conflict' => $this->pendingConflict,
            'rows' => array_map(static fn (FabricRegistryImportRowResult $row): array => $row->toArray(), $this->rows),
            'import_run_id' => $this->importRun?->id,
        ];
    }

    /**
     * Сериализация для catalog_import_runs.stats_json (без построчного отчёта — иначе >64 KB).
     *
     * @return array<string, mixed>
     */
    public function toStatsPayload(): array
    {
        $payload = $this->toArray();
        unset($payload['rows']);

        return $payload;
    }
}
