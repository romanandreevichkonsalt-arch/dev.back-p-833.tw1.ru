<?php

namespace app\commands;

use app\services\catalog\FabricLibraryArchiveLauncher;
use app\services\import\fabric\FabricRegistryImportOptions;
use app\services\import\fabric\FabricRegistryImporter;
use app\services\import\fabric\FabricRegistryImportRowResult;
use app\services\media\FabricSwatchVariantRegenerator;
use yii\console\Controller;
use yii\console\ExitCode;

class FabricRegistryController extends Controller
{
    public bool $dryRun = false;
    public bool $media = true;
    public bool $update = false;
    public string $onConflict = 'abort';

    public function options($actionID): array
    {
        $options = array_merge(parent::options($actionID), ['dryRun', 'media', 'update']);
        if ($actionID === 'import') {
            $options[] = 'onConflict';
        }
        if ($actionID === 'regenerate-swatch-variants') {
            $options[] = 'dryRun';
        }

        return $options;
    }

    public function optionAliases(): array
    {
        return [
            'd' => 'dryRun',
            'm' => 'media',
            'u' => 'update',
            'c' => 'onConflict',
        ];
    }

    public function actionImport(string $file): int
    {
        $options = new FabricRegistryImportOptions();
        $options->dryRun = $this->dryRun;
        $options->importMedia = $this->media;
        $options->updateExisting = $this->update;
        $options->conflictResolution = $this->onConflict;
        $options->filename = basename($file);

        $importer = new FabricRegistryImporter();
        $result = $importer->import($file, $options);

        $stats = $result->stats;
        $this->stdout(sprintf(
            "Import finished: created=%d updated=%d skipped=%d conflicts=%d errors=%d aborted=%s\n",
            $stats['links_created'] ?? 0,
            $stats['links_updated'] ?? 0,
            $stats['rows_skipped'] ?? 0,
            $stats['links_conflict'] ?? 0,
            $stats['errors'] ?? 0,
            $result->aborted ? 'yes' : 'no'
        ));

        foreach ($result->rows as $row) {
            $photoStatus = $row->photoImported ? 'ok' : ($row->photoSkipped ? 'skipped' : 'none');
            $this->stdout(sprintf(
                "  row %d [%s] %s / %s: photo=%s (%s)\n",
                $row->rowNumber,
                $row->action,
                $row->collectionName,
                $row->designCode,
                $photoStatus,
                implode('; ', $row->messages)
            ));
        }

        if ($result->aborted || ($stats['errors'] ?? 0) > 0) {
            return ExitCode::UNSPECIFIED_ERROR;
        }

        if (!$options->dryRun) {
            FabricLibraryArchiveLauncher::rebuildNow();
        }

        return ExitCode::OK;
    }

    public function actionRepairMedia(): int
    {
        $service = new \app\services\import\fabric\FabricMediaRepairService();
        $result = $service->repairMissingSwatches();

        $this->stdout(sprintf(
            "Repair finished: repaired=%d failed=%d skipped=%d\n",
            $result['repaired'],
            $result['failed'],
            $result['skipped']
        ));

        foreach ($result['messages'] as $message) {
            $this->stdout('  ' . $message . "\n");
        }

        return ($result['failed'] ?? 0) > 0 ? ExitCode::UNSPECIFIED_ERROR : ExitCode::OK;
    }

    public function actionRegenerateSwatchVariants(): int
    {
        $service = new FabricSwatchVariantRegenerator();
        $result = $service->regenerateMissing($this->dryRun);

        $this->stdout(sprintf(
            "Fabric swatch variants: processed=%d regenerated=%d skipped=%d failed=%d%s\n",
            $result['processed'],
            $result['regenerated'],
            $result['skipped'],
            $result['failed'],
            $this->dryRun ? ' (dry-run)' : ''
        ));

        foreach ($result['messages'] as $message) {
            $this->stdout('  ' . $message . "\n");
        }

        return ($result['failed'] ?? 0) > 0 ? ExitCode::UNSPECIFIED_ERROR : ExitCode::OK;
    }
}
