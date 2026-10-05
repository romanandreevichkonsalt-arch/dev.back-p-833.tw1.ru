<?php

namespace app\services\import\surface;

class SurfaceMaterialRegistryImportOptions
{
    public const CONFLICT_SKIP = 'skip';
    public const CONFLICT_SKIP_ALL = 'skip_all';
    public const CONFLICT_UPDATE = 'update';
    public const CONFLICT_ABORT = 'abort';

    public bool $dryRun = false;
    public bool $updateExisting = false;
    public bool $importMedia = true;
    public bool $asyncMode = false;
    public ?int $userId = null;
    public ?int $importRunId = null;
    public string $filename = '';
    public string $importSource = 'surface_material_registry';
    public ?string $conflictResolution = null;
    public ?string $conflictResolutionOnce = null;

    public function shouldImportMedia(): bool
    {
        return $this->importMedia && $this->shouldWrite();
    }

    public function shouldWrite(): bool
    {
        return !$this->dryRun;
    }

    public function resolveConflictAction(): string
    {
        if ($this->conflictResolutionOnce !== null && $this->conflictResolutionOnce !== '') {
            return $this->conflictResolutionOnce;
        }

        if ($this->conflictResolution !== null && $this->conflictResolution !== '') {
            return $this->conflictResolution;
        }

        if ($this->updateExisting) {
            return self::CONFLICT_UPDATE;
        }

        return self::CONFLICT_ABORT;
    }

    public function clearConflictResolutionOnce(): void
    {
        $this->conflictResolutionOnce = null;
    }
}
