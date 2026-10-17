<?php

namespace app\services\import\fabric;

use app\models\CatalogImportMediaQueue;
use app\models\CatalogImportRun;
use app\services\cache\ApiCacheInvalidator;
use app\services\catalog\FabricLibraryArchiveLauncher;
use app\services\import\ImportRunWorkerLauncher;

/**
 * Завершение fabric-import run после строк и после очереди медиа.
 */
final class FabricImportRunCompletion
{
    public function pendingFabricMediaCount(int $runId): int
    {
        return (int)CatalogImportMediaQueue::find()
            ->where([
                'import_run_id' => $runId,
                'status' => CatalogImportMediaQueue::STATUS_PENDING,
                'entity_type' => 'fabric',
            ])
            ->count();
    }

    public function runWantsMedia(CatalogImportRun $run): bool
    {
        $options = $run->getOptions();

        return (bool)($options['import_media'] ?? true);
    }

    public function canMarkCompleted(CatalogImportRun $run): bool
    {
        if ($run->total_rows <= 0 || $run->processed_rows < $run->total_rows) {
            return false;
        }

        if ($run->type === CatalogImportRun::TYPE_FABRIC && $this->runWantsMedia($run)) {
            return $this->pendingFabricMediaCount((int)$run->id) === 0;
        }

        return true;
    }

    public function finalizeCompleted(CatalogImportRun $run): void
    {
        if (!$this->canMarkCompleted($run)) {
            return;
        }

        if (in_array($run->status, [
            CatalogImportRun::STATUS_COMPLETED,
            CatalogImportRun::STATUS_FAILED,
            CatalogImportRun::STATUS_ABORTED,
            CatalogImportRun::STATUS_AWAITING_CONFLICT,
        ], true)) {
            return;
        }

        $run->status = CatalogImportRun::STATUS_COMPLETED;
        $run->phase = CatalogImportRun::PHASE_DONE;
        $run->phase_message = sprintf(
            'Обработано %d строк из %d',
            (int)$run->total_rows,
            (int)$run->total_rows
        );
        if ($run->finished_at === null) {
            $run->finished_at = date('Y-m-d H:i:s');
        }
        $run->save(false, ['status', 'phase', 'phase_message', 'finished_at']);

        $this->syncFinalMediaStats($run);

        $run->refresh();
        if ($this->hasDeferredVariants($run)) {
            $run->phase_message = sprintf(
                'Обработано %d строк из %d. Превью webp создаются в фоне.',
                (int)$run->total_rows,
                (int)$run->total_rows
            );
            $run->save(false, ['phase_message']);
        }

        if ($run->file_path !== null && $run->file_path !== '' && is_file($run->file_path)) {
            @unlink($run->file_path);
            $run->file_path = null;
            $run->save(false, ['file_path']);
        }

        ApiCacheInvalidator::touch();

        $archiveResult = FabricLibraryArchiveLauncher::rebuildNow();
        if (!($archiveResult['success'] ?? false)) {
            \Yii::warning(
                'Не удалось собрать архив текстур после импорта: ' . ($archiveResult['message'] ?? ''),
                __METHOD__
            );
        }

        $this->dispatchDeferredVariants($run);
    }

    public function hasDeferredVariants(CatalogImportRun $run): bool
    {
        $payload = $run->getStats();
        if (!empty($payload['post_process']['variants_done'])) {
            return false;
        }

        $ids = $payload['variant_media_ids'] ?? [];

        return is_array($ids) && $ids !== [];
    }

    private function dispatchDeferredVariants(CatalogImportRun $run): void
    {
        if (!$this->hasDeferredVariants($run)) {
            return;
        }

        ImportRunWorkerLauncher::dispatch(
            'fabric-import/process-variants',
            (int)$run->id,
            'fabric-import'
        );
    }

    private function syncFinalMediaStats(CatalogImportRun $run): void
    {
        $runId = (int)$run->id;
        $payload = $run->getStats();
        if (!isset($payload['stats']) || !is_array($payload['stats'])) {
            $payload['stats'] = [];
        }

        $payload['stats']['photo_queued'] = 0;
        $payload['stats']['photo_imported'] = (int)CatalogImportMediaQueue::find()
            ->where([
                'import_run_id' => $runId,
                'entity_type' => 'fabric',
                'status' => CatalogImportMediaQueue::STATUS_DONE,
            ])
            ->count();

        $run->setStats($payload);
        $run->save(false, ['stats_json']);
    }
}
