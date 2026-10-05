<?php

namespace app\services\import\surface;

class SurfaceMaterialRegistryImportRowResult
{
    public const ACTION_SKIP = 'skip';
    public const ACTION_CREATE = 'create';
    public const ACTION_UPDATE = 'update';
    public const ACTION_CONFLICT = 'conflict';
    public const ACTION_ERROR = 'error';
    public const ACTION_PREVIEW = 'preview';

    public int $rowNumber;
    public string $action = self::ACTION_PREVIEW;
    public string $materialType = '';
    public string $name = '';
    /** @var string[] */
    public array $warnings = [];
    /** @var string[] */
    public array $messages = [];
    /** @var array<string, mixed>|null */
    public ?array $conflict = null;
    public ?int $materialId = null;
    public bool $photoSkipped = false;
    public bool $photoImported = false;
    public bool $textureSkipped = false;
    public bool $textureImported = false;
    public int $collectionsLinked = 0;

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
            'material_type' => $this->materialType,
            'name' => $this->name,
            'collection' => $this->materialType,
            'design_code' => $this->name,
            'warnings' => $this->warnings,
            'messages' => $this->messages,
            'conflict' => $this->conflict,
            'material_id' => $this->materialId,
            'photo_skipped' => $this->photoSkipped,
            'photo_imported' => $this->photoImported,
            'texture_skipped' => $this->textureSkipped,
            'texture_imported' => $this->textureImported,
            'collections_linked' => $this->collectionsLinked,
        ];
    }
}
