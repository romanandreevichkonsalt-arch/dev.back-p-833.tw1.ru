<?php

namespace app\services\import\catalog;

class CatalogModelImportResult
{
    /** @var array<string, int> */
    public array $stats = [
        'models_created' => 0,
        'models_updated' => 0,
        'models_skipped' => 0,
        'models_conflict' => 0,
        'rows_skipped' => 0,
        'errors' => 0,
    ];

    /** @var CatalogModelImportRowResult[] */
    public array $rows = [];

    public bool $aborted = false;
    public bool $success = true;

    /** @var list<array{row_number:int,collection_name:string,model_label:string,model_id:int}> */
    public array $conflicts = [];

    public ?array $pendingConflict = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'stats' => $this->stats,
            'aborted' => $this->aborted,
            'success' => $this->success,
            'rows' => array_map(static function (CatalogModelImportRowResult $row): array {
                return [
                    'action' => $row->action,
                    'row_number' => $row->rowNumber,
                    'collection_name' => $row->collectionName,
                    'model_label' => $row->modelLabel,
                    'messages' => $row->messages,
                ];
            }, $this->rows),
            'conflicts' => $this->conflicts,
            'pending_conflict' => $this->pendingConflict,
        ];
    }
}
