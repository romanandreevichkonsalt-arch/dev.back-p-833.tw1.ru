<?php

namespace app\services\import\surface;

use app\models\CatalogImportRun;

class SurfaceMaterialRegistryImportResult
{
    public bool $success = true;
    public bool $aborted = false;
    public ?CatalogImportRun $importRun = null;
    /** @var SurfaceMaterialRegistryImportRowResult[] */
    public array $rows = [];
    /** @var array<string, int> */
    public array $stats = [
        'rows_total' => 0,
        'rows_skipped' => 0,
        'materials_created' => 0,
        'materials_updated' => 0,
        'materials_conflict' => 0,
        'collection_links_added' => 0,
        'photo_imported' => 0,
        'photo_skipped' => 0,
        'texture_imported' => 0,
        'texture_skipped' => 0,
        'errors' => 0,
        'links_created' => 0,
        'links_updated' => 0,
        'links_conflict' => 0,
    ];
    /** @var array<string, mixed>|null */
    public ?array $pendingConflict = null;

    public function addRow(SurfaceMaterialRegistryImportRowResult $row): void
    {
        $this->rows[] = $row;
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
            'rows' => array_map(
                static fn (SurfaceMaterialRegistryImportRowResult $row): array => $row->toArray(),
                $this->rows
            ),
            'import_run_id' => $this->importRun?->id,
        ];
    }
}
