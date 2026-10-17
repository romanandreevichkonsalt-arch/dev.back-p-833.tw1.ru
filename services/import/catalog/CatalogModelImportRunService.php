<?php

namespace app\services\import\catalog;

use app\models\CatalogImportRun;
use app\services\import\ImportRunWorkerLauncher;
use app\services\import\SpreadsheetFormatValidator;
use Yii;
use yii\helpers\FileHelper;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;
use yii\web\UploadedFile;

class CatalogModelImportRunService
{
    public function __construct(
        private readonly CatalogModelImporter $importer = new CatalogModelImporter(),
        private readonly CatalogModelImportRunCompletion $completion = new CatalogModelImportRunCompletion(),
        private readonly CatalogModelImportMediaQueueProcessor $mediaProcessor = new CatalogModelImportMediaQueueProcessor(),
    ) {
    }

    public function startFromUpload(UploadedFile $file, CatalogModelImportOptions $options, ?int $userId): CatalogImportRun
    {
        $extension = strtolower((string)$file->extension);
        if (!in_array($extension, ['xlsx', 'xls'], true)) {
            throw new BadRequestHttpException('Допустимы только файлы Excel (.xlsx, .xls).');
        }

        $importDir = Yii::getAlias('@runtime/catalog-model-import');
        FileHelper::createDirectory($importDir);
        $storedPath = $importDir . '/' . uniqid('price_list_', true) . '.' . $extension;

        if (!$file->saveAs($storedPath)) {
            throw new BadRequestHttpException('Не удалось сохранить загруженный файл.');
        }

        try {
            SpreadsheetFormatValidator::assertReadableExcel($storedPath);
        } catch (\InvalidArgumentException $e) {
            @unlink($storedPath);
            throw new BadRequestHttpException('Ошибка импорта: ' . $e->getMessage(), 0, $e);
        }

        $this->abortRunsWithoutActiveWorker();

        $activeRunId = CatalogImportRun::find()
            ->select('id')
            ->where(['type' => CatalogImportRun::TYPE_MODEL])
            ->andWhere(['status' => [
                CatalogImportRun::STATUS_QUEUED,
                CatalogImportRun::STATUS_PARSING,
                CatalogImportRun::STATUS_PROCESSING,
                CatalogImportRun::STATUS_AWAITING_CONFLICT,
            ]])
            ->orderBy(['id' => SORT_DESC])
            ->scalar();
        if ($activeRunId !== false && (int)$activeRunId > 0) {
            @unlink($storedPath);
            throw new BadRequestHttpException(sprintf(
                'Уже выполняется импорт моделей (запуск #%d). Дождитесь завершения или отмените зависший запуск.',
                (int)$activeRunId
            ));
        }

        $run = new CatalogImportRun([
            'type' => CatalogImportRun::TYPE_MODEL,
            'user_id' => $userId,
            'filename' => $options->filename !== null && $options->filename !== ''
                ? $options->filename
                : (string)$file->name,
            'sheet' => CatalogModelImportSpreadsheetReader::SHEET_MODELS,
            'file_path' => $storedPath,
            'status' => CatalogImportRun::STATUS_QUEUED,
            'phase' => CatalogImportRun::PHASE_QUEUED,
            'phase_message' => 'Файл принят, ожидание обработки…',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $run->setOptions($this->optionsToArray($options));
        $run->save(false);

        $this->dispatchWorker((int)$run->id);

        return $run;
    }

    public function process(int $runId): void
    {
        $run = $this->findRun($runId);
        if (!$run->isActive() && $run->status !== CatalogImportRun::STATUS_AWAITING_CONFLICT) {
            return;
        }

        if ($run->file_path === null || $run->file_path === '' || !is_file($run->file_path)) {
            $run->status = CatalogImportRun::STATUS_FAILED;
            $run->error_message = 'Файл импорта не найден.';
            $run->finished_at = date('Y-m-d H:i:s');
            $run->save(false);

            return;
        }

        $lockPath = $this->lockPath($runId);
        $lockHandle = fopen($lockPath, 'c+');
        if ($lockHandle === false || !flock($lockHandle, LOCK_EX | LOCK_NB)) {
            if ($lockHandle !== false) {
                fclose($lockHandle);
            }

            return;
        }

        try {
            $run->status = CatalogImportRun::STATUS_PROCESSING;
            $run->phase = CatalogImportRun::PHASE_PROCESSING;
            if ($run->started_at === null) {
                $run->started_at = date('Y-m-d H:i:s');
            }
            $run->save(false);

            $options = $this->optionsFromRun($run);
            $options->asyncMode = true;
            $options->importRunId = $runId;

            $accumulated = $this->buildResultFromRun($run);
            $startIndex = (int)$run->resume_row_index;

            $result = $this->importer->import(
                $run->file_path,
                $options,
                $run,
                $startIndex,
                $accumulated
            );

            $run = $this->findRun($runId);

            if ($result->aborted && $run->status === CatalogImportRun::STATUS_AWAITING_CONFLICT) {
                return;
            }

            if ($run->phase === CatalogImportRun::PHASE_MEDIA && $this->completion->pendingModelMediaCount($runId) > 0) {
                $this->dispatchMediaWorker($runId);

                return;
            }

            if ($this->completion->canMarkCompleted($run)) {
                $this->completion->finalizeCompleted($run);
            }
        } catch (\Throwable $exception) {
            Yii::error($exception->getMessage(), __METHOD__);
            $run = $this->findRun($runId);
            if (!$run->isTerminal()) {
                $run->status = CatalogImportRun::STATUS_FAILED;
                $run->error_message = $exception->getMessage();
                $run->finished_at = date('Y-m-d H:i:s');
                $run->save(false);
            }
        } finally {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
            @unlink($lockPath);
        }
    }

    public function processMediaQueue(int $runId, int $limit = 40, int $concurrency = 8): void
    {
        $run = $this->findRun($runId);
        if ($run->isTerminal()) {
            return;
        }

        $lockPath = $this->lockPath($runId) . '.media';
        $lockHandle = fopen($lockPath, 'c+');
        if ($lockHandle === false || !flock($lockHandle, LOCK_EX | LOCK_NB)) {
            if ($lockHandle !== false) {
                fclose($lockHandle);
            }

            return;
        }

        try {
            $run->status = CatalogImportRun::STATUS_PROCESSING;
            $run->phase = CatalogImportRun::PHASE_MEDIA;
            $run->save(false, ['status', 'phase']);

            $batch = $this->mediaProcessor->processRun($runId, $limit, $concurrency);
            $run = $this->findRun($runId);

            if ($batch['continue']) {
                $this->dispatchMediaWorker($runId);

                return;
            }

            if ($this->completion->canMarkCompleted($run)) {
                $this->completion->finalizeCompleted($run);
            }
        } catch (\Throwable $exception) {
            Yii::error($exception->getMessage(), __METHOD__);
            $run = $this->findRun($runId);
            if (!$run->isTerminal()) {
                $run->status = CatalogImportRun::STATUS_FAILED;
                $run->error_message = $exception->getMessage();
                $run->finished_at = date('Y-m-d H:i:s');
                $run->save(false);
            }
        } finally {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
            @unlink($lockPath);
        }
    }

    public function resolveConflict(int $runId, string $action): CatalogImportRun
    {
        $run = $this->findRun($runId);
        if ($run->status !== CatalogImportRun::STATUS_AWAITING_CONFLICT) {
            throw new BadRequestHttpException('Импорт не ожидает разрешения конфликта.');
        }

        if (!in_array($action, [
            CatalogModelImportOptions::CONFLICT_SKIP,
            CatalogModelImportOptions::CONFLICT_SKIP_ALL,
            CatalogModelImportOptions::CONFLICT_UPDATE,
        ], true)) {
            throw new BadRequestHttpException('Недопустимое действие для конфликта.');
        }

        $options = $run->getOptions();
        if ($action === CatalogModelImportOptions::CONFLICT_SKIP_ALL) {
            $options['conflict_resolution'] = CatalogModelImportOptions::CONFLICT_SKIP;
            unset($options['conflict_resolution_once']);
        } else {
            $options['conflict_resolution'] = CatalogModelImportOptions::CONFLICT_ABORT;
            $options['conflict_resolution_once'] = $action;
        }
        $run->setOptions($options);

        $payload = $run->getStats();
        if (isset($payload['rows']) && is_array($payload['rows'])) {
            $payload['rows'] = array_slice($payload['rows'], 0, (int)$run->resume_row_index);
            $payload['pending_conflict'] = null;
            $payload['aborted'] = false;
            $run->setStats($payload);
        }

        $run->status = CatalogImportRun::STATUS_QUEUED;
        $run->phase = CatalogImportRun::PHASE_QUEUED;
        $run->phase_message = 'Продолжение импорта…';
        $run->finished_at = null;
        $run->save(false);

        $this->dispatchWorker($runId);

        return $run;
    }

    /**
     * @return array<string, mixed>
     */
    public function abortRun(int $runId): CatalogImportRun
    {
        $run = $this->findRun($runId);
        if ($run->isTerminal()) {
            return $run;
        }

        if ($this->isRunWorkerLocked($runId)) {
            throw new BadRequestHttpException('Импорт ещё выполняется в фоне — дождитесь завершения.');
        }

        $this->markRunAborted($run, 'Импорт отменён вручную.');

        return $run;
    }

    public function getStatusPayload(int $runId): array
    {
        $this->abortRunsWithoutActiveWorker();

        $run = $this->findRun($runId);
        $this->completion->finalizeCompleted($run);
        $this->maybeRedispatchStalledWorker($run);

        if ($run->status === CatalogImportRun::STATUS_QUEUED) {
            $createdAt = strtotime((string)$run->created_at);
            if ($createdAt !== false && time() - $createdAt >= 3) {
                $this->dispatchWorker($runId);
            }
        }

        $payload = $run->getStats();
        $stats = is_array($payload['stats'] ?? null) ? $payload['stats'] : [];
        $processed = (int)$run->processed_rows;
        $total = (int)$run->total_rows;
        $mediaProcessed = $run->getMediaProcessedRows();
        $mediaTotal = $run->getMediaTotal();
        if ($run->phase === CatalogImportRun::PHASE_MEDIA && $mediaTotal <= 0) {
            $mediaTotal = max($mediaTotal, $this->completion->pendingModelMediaCount($runId));
        }

        return [
            'id' => (int)$run->id,
            'status' => $run->status,
            'phase' => $run->phase,
            'processed' => $processed,
            'total' => $total,
            'mediaProcessed' => $mediaProcessed,
            'mediaTotal' => $mediaTotal,
            'message' => (string)$run->phase_message,
            'stats' => $stats,
            'pendingConflict' => $payload['pending_conflict'] ?? null,
            'errorMessage' => $run->error_message,
            'filename' => $run->filename,
            'finished' => $run->isTerminal()
                || $run->status === CatalogImportRun::STATUS_AWAITING_CONFLICT,
            'success' => $run->status === CatalogImportRun::STATUS_COMPLETED,
        ];
    }

    public function dispatchWorker(int $runId): void
    {
        ImportRunWorkerLauncher::dispatch('catalog-model-import/run', $runId, 'catalog-model-import');
    }

    public function dispatchMediaWorker(int $runId): void
    {
        ImportRunWorkerLauncher::dispatch('catalog-model-import/process-media-queue', $runId, 'catalog-model-import');
    }

    private function maybeRedispatchStalledWorker(CatalogImportRun $run): void
    {
        if ($run->status !== CatalogImportRun::STATUS_PROCESSING) {
            return;
        }

        if ($run->phase === CatalogImportRun::PHASE_MEDIA) {
            $this->maybeRedispatchStalledMediaWorker($run);

            return;
        }

        $lockPath = $this->lockPath((int)$run->id);
        $lockHandle = @fopen($lockPath, 'c+');
        if ($lockHandle === false) {
            return;
        }

        if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
            fclose($lockHandle);

            return;
        }

        flock($lockHandle, LOCK_UN);
        fclose($lockHandle);
        @unlink($lockPath);
        $this->dispatchWorker((int)$run->id);
    }

    private function maybeRedispatchStalledMediaWorker(CatalogImportRun $run): void
    {
        if ($this->completion->pendingModelMediaCount((int)$run->id) <= 0) {
            return;
        }

        $lockPath = $this->lockPath((int)$run->id) . '.media';
        $lockHandle = @fopen($lockPath, 'c+');
        if ($lockHandle === false) {
            return;
        }

        if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
            fclose($lockHandle);

            return;
        }

        flock($lockHandle, LOCK_UN);
        fclose($lockHandle);
        @unlink($lockPath);
        $this->dispatchMediaWorker((int)$run->id);
    }

    private function findRun(int $runId): CatalogImportRun
    {
        $run = CatalogImportRun::findOne([
            'id' => $runId,
            'type' => CatalogImportRun::TYPE_MODEL,
        ]);
        if ($run === null) {
            throw new NotFoundHttpException('Импорт не найден.');
        }

        return $run;
    }

    private function lockPath(int $runId): string
    {
        $dir = Yii::getAlias('@runtime/catalog-model-import');
        FileHelper::createDirectory($dir);

        return $dir . '/run-' . $runId . '.lock';
    }

    private function mediaLockPath(int $runId): string
    {
        return $this->lockPath($runId) . '.media';
    }

    private function isRunWorkerLocked(int $runId): bool
    {
        return $this->isLockHeldByWorker($this->lockPath($runId))
            || $this->isLockHeldByWorker($this->mediaLockPath($runId));
    }

    private function isLockHeldByWorker(string $lockPath): bool
    {
        $lockHandle = @fopen($lockPath, 'c+');
        if ($lockHandle === false) {
            return false;
        }

        if (flock($lockHandle, LOCK_EX | LOCK_NB)) {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);

            return false;
        }

        fclose($lockHandle);

        return true;
    }

    private function abortRunsWithoutActiveWorker(): void
    {
        $runs = CatalogImportRun::find()
            ->where(['type' => CatalogImportRun::TYPE_MODEL])
            ->andWhere(['status' => [
                CatalogImportRun::STATUS_QUEUED,
                CatalogImportRun::STATUS_PARSING,
                CatalogImportRun::STATUS_PROCESSING,
            ]])
            ->all();

        foreach ($runs as $run) {
            if ($this->isRunWorkerLocked((int)$run->id)) {
                continue;
            }

            $this->markRunAborted(
                $run,
                'Импорт остановлен: фоновый процесс не активен (перезапуск сервера или сбой воркера).'
            );
        }
    }

    private function markRunAborted(CatalogImportRun $run, string $message): void
    {
        $run->status = CatalogImportRun::STATUS_ABORTED;
        $run->phase = CatalogImportRun::PHASE_DONE;
        $run->phase_message = $message;
        $run->assignErrorMessage($message);
        if ($run->finished_at === null) {
            $run->finished_at = date('Y-m-d H:i:s');
        }
        $run->save(false);
    }

    /**
     * @return array<string, mixed>
     */
    private function optionsToArray(CatalogModelImportOptions $options): array
    {
        return [
            'update_existing' => $options->updateExisting,
            'conflict_resolution' => $options->conflictResolution,
            'dry_run' => $options->dryRun,
            'filename' => $options->filename,
            'skip_if_no_fabric' => $options->skipIfNoFabric,
            'import_media' => $options->importMedia,
        ];
    }

    private function optionsFromRun(CatalogImportRun $run): CatalogModelImportOptions
    {
        $data = $run->getOptions();
        $options = new CatalogModelImportOptions();
        $options->updateExisting = (bool)($data['update_existing'] ?? false);
        $options->dryRun = (bool)($data['dry_run'] ?? false);
        $options->conflictResolution = isset($data['conflict_resolution']) && $data['conflict_resolution'] !== ''
            ? (string)$data['conflict_resolution']
            : CatalogModelImportOptions::CONFLICT_SKIP;
        $options->conflictResolutionOnce = isset($data['conflict_resolution_once']) && $data['conflict_resolution_once'] !== ''
            ? (string)$data['conflict_resolution_once']
            : null;
        $options->filename = (string)($data['filename'] ?? $run->filename);
        $options->skipIfNoFabric = (bool)($data['skip_if_no_fabric'] ?? false);
        $options->importMedia = (bool)($data['import_media'] ?? true);
        $options->userId = $run->user_id !== null ? (int)$run->user_id : null;

        return $options;
    }

    private function buildResultFromRun(CatalogImportRun $run): CatalogModelImportResult
    {
        $payload = $run->getStats();
        if ($payload === []) {
            return new CatalogModelImportResult();
        }

        $result = new CatalogModelImportResult();
        $result->success = (bool)($payload['success'] ?? true);
        $result->aborted = (bool)($payload['aborted'] ?? false);
        $savedStats = is_array($payload['stats'] ?? null) ? $payload['stats'] : [];
        $result->stats = array_merge($result->stats, $savedStats);
        $result->pendingConflict = is_array($payload['pending_conflict'] ?? null)
            ? $payload['pending_conflict']
            : null;
        $result->conflicts = is_array($payload['conflicts'] ?? null) ? $payload['conflicts'] : [];

        foreach ((array)($payload['rows'] ?? []) as $rowData) {
            if (!is_array($rowData)) {
                continue;
            }
            $row = new CatalogModelImportRowResult();
            $row->rowNumber = (int)($rowData['row_number'] ?? 0);
            $row->collectionName = (string)($rowData['collection_name'] ?? '');
            $row->modelLabel = (string)($rowData['model_label'] ?? '');
            $row->action = (string)($rowData['action'] ?? 'skipped');
            $row->messages = is_array($rowData['messages'] ?? null) ? $rowData['messages'] : [];
            $result->rows[] = $row;
        }

        return $result;
    }
}
