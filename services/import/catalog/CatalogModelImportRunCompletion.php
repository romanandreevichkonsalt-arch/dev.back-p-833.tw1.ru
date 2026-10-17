<?php

namespace app\services\import\catalog;

use app\models\CatalogImportMediaQueue;
use app\models\CatalogImportRun;
use app\services\cache\ApiCacheInvalidator;

final class CatalogModelImportRunCompletion
{
    /** @var list<string> */
    private const MODEL_MEDIA_ENTITY_TYPES = ['model_interior', 'model_dimension'];

    public function pendingModelMediaCount(int $runId): int
    {
        return (int)CatalogImportMediaQueue::find()
            ->where([
                'import_run_id' => $runId,
                'status' => CatalogImportMediaQueue::STATUS_PENDING,
            ])
            ->andWhere(['entity_type' => self::MODEL_MEDIA_ENTITY_TYPES])
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

        if ($run->type === CatalogImportRun::TYPE_MODEL && $this->runWantsMedia($run)) {
            return $this->pendingModelMediaCount((int)$run->id) === 0;
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

        if ($run->file_path !== null && $run->file_path !== '' && is_file($run->file_path)) {
            @unlink($run->file_path);
            $run->file_path = null;
            $run->save(false, ['file_path']);
        }

        ApiCacheInvalidator::touch();
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
                'status' => CatalogImportMediaQueue::STATUS_DONE,
            ])
            ->andWhere(['entity_type' => self::MODEL_MEDIA_ENTITY_TYPES])
            ->count();

        $run->setStats($payload);
        $run->save(false, ['stats_json']);
    }
}
