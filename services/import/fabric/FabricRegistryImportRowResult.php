<?php

namespace app\services\import\fabric;

class FabricRegistryImportRowResult
{
    public const ACTION_SKIP = 'skip';
    public const ACTION_CREATE = 'create';
    public const ACTION_UPDATE = 'update';
    public const ACTION_CONFLICT = 'conflict';
    public const ACTION_ERROR = 'error';
    public const ACTION_PREVIEW = 'preview';

    public int $rowNumber;
    public string $action = self::ACTION_PREVIEW;
    public string $collectionName = '';
    public string $designCode = '';
    public ?string $colorLabel = null;
    /** @var string[] */
    public array $warnings = [];
    /** @var string[] */
    public array $messages = [];
    /** @var array<string, mixed>|null */
    public ?array $conflict = null;
    public ?int $collectionId = null;
    public ?int $linkId = null;
    public bool $photoSkipped = false;
    public bool $photoImported = false;

    public function addWarning(string $message): void
    {
        $this->warnings[] = $message;
    }

    public function addMessage(string $message): void
    {
        $this->messages[] = $message;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'row_number' => $this->rowNumber,
            'action' => $this->action,
            'collection' => $this->collectionName,
            'design_code' => $this->designCode,
            'color' => $this->colorLabel,
            'warnings' => $this->warnings,
            'messages' => $this->messages,
            'conflict' => $this->conflict,
            'collection_id' => $this->collectionId,
            'link_id' => $this->linkId,
            'photo_skipped' => $this->photoSkipped,
            'photo_imported' => $this->photoImported,
        ];
    }
}
