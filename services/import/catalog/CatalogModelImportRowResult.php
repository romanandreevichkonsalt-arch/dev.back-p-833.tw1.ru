<?php

namespace app\services\import\catalog;

class CatalogModelImportRowResult
{
    public string $action = 'skipped';
    public int $rowNumber = 0;
    public string $collectionName = '';
    public string $modelLabel = '';
    /** @var list<string> */
    public array $messages = [];

    public function __construct(
        public readonly string $status = 'skipped',
    ) {
        $this->action = $status;
    }
}
