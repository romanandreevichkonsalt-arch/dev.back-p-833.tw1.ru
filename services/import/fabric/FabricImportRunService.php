<?php

namespace app\services\import\fabric;

use app\models\CatalogImportRun;
use app\services\cache\ApiCacheInvalidator;
use app\services\catalog\FabricLibraryArchiveLauncher;
use app\services\import\ImportRunWorkerLauncher;
use app\services\import\SpreadsheetFormatValidator;
use Yii;
use yii\helpers\FileHelper;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;
use yii\web\UploadedFile;

class FabricImportRunService
{
    public function __construct(
        private readonly FabricRegistryImporter $importer = new FabricRegistryImporter(),
        private readonly FabricImportRunCompletion $completion = new FabricImportRunCompletion(),
        private readonly FabricImportMediaQueueProcessor $mediaProcessor = new FabricImportMediaQueueProcessor(),
        private readonly FabricImportRunVariantService $variantProcessor = new FabricImportRunVariantService(),
    ) {
    }

    public function startFromUpload(UploadedFile $file, FabricRegistryImportOptions $options, ?int $userId): CatalogImportRun
    {
        $extension = strtolower((string)$file->extension);
        if (!in_array($extension, ['xlsx', 'xls'], true)) {
            throw new BadRequestHttpException('Допустимы только файлы Excel (.xlsx, .xls).');
        }

        $importDir = Yii::getAlias('@runtime/fabric-import');
        FileHelper::createDirectory($importDir);
        $storedPath = $importDir . '/' . uniqid('registry_', true) . '.' . $extension;

        if (!$file->saveAs($storedPath)) {
            throw new BadRequestHttpException('Не удалось сохранить загруженный файл.');
        }

        try {
            SpreadsheetFormatValidator::assertReadableExcel($storedPath);
        } catch (\InvalidArgumentException $e) {
            @unlink($storedPath);
            throw new BadRequestHttpException('Ошибка импорта: ' . $e->getMessage(), 0, $e);
        }

        $run = new CatalogImportRun([
            'type' => CatalogImportRun::TYPE_FABRIC,
            'user_id' => $userId,
            'filename' => $options->filename !== '' ? $options->filename : (string)$file->name,
            'sheet' => FabricRegistrySpreadsheetReader::SHEET_FABRICS,
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
            $run->assignErrorMessage('Файл импорта не найден.');
            $run->finished_at = date('Y-m-d H:i:s');
            $run->save(false, ['status', 'error_message', 'finished_at']);

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

            if ($run->phase === CatalogImportRun::PHASE_MEDIA && $this->completion->pendingFabricMediaCount($runId) > 0) {
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
                $run->assignErrorMessage($exception->getMessage());
                $run->finished_at = date('Y-m-d H:i:s');
                $run->save(false, ['status', 'error_message', 'finished_at']);
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
                $run->assignErrorMessage($exception->getMessage());
                $run->finished_at = date('Y-m-d H:i:s');
                $run->save(false, ['status', 'error_message', 'finished_at']);
            }
        } finally {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
            @unlink($lockPath);
        }
    }

    public function dispatchMediaWorker(int $runId): void
    {
        ImportRunWorkerLauncher::dispatch('fabric-import/process-media-queue', $runId, 'fabric-import');
    }

    public function processVariants(int $runId): void
    {
        $run = $this->findRun($runId);
        if (!$this->completion->hasDeferredVariants($run)) {
            return;
        }

        if ($this->completion->pendingFabricMediaCount($runId) > 0) {
            return;
        }

        $lockPath = $this->lockPath($runId) . '.variants';
        $lockHandle = fopen($lockPath, 'c+');
        if ($lockHandle === false || !flock($lockHandle, LOCK_EX | LOCK_NB)) {
            if ($lockHandle !== false) {
                fclose($lockHandle);
            }

            return;
        }

        try {
            $run = $this->findRun($runId);
            $result = $this->variantProcessor->process($run);

            if ($result['continueInNextWorker']) {
                $this->dispatchVariantWorker($runId);

                return;
            }

            $run = $this->findRun($runId);
            $payload = $run->getStats();
            if (!isset($payload['post_process']) || !is_array($payload['post_process'])) {
                $payload['post_process'] = [];
            }
            $payload['post_process']['variants_done'] = true;
            $run->setStats($payload);
            $run->save(false, ['stats_json']);

            ApiCacheInvalidator::touch();
            $archiveResult = FabricLibraryArchiveLauncher::rebuildNow();
            if (!($archiveResult['success'] ?? false)) {
                Yii::warning(
                    'Не удалось пересобрать архив после webp: ' . ($archiveResult['message'] ?? ''),
                    __METHOD__
                );
            }
        } catch (\Throwable $exception) {
            Yii::error($exception->getMessage(), __METHOD__);
        } finally {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
            @unlink($lockPath);
        }
    }

    public function dispatchVariantWorker(int $runId): void
    {
        ImportRunWorkerLauncher::dispatch('fabric-import/process-variants', $runId, 'fabric-import');
    }

    public function resolveConflict(int $runId, string $action): CatalogImportRun
    {
        $run = $this->findRun($runId);
        if ($run->status !== CatalogImportRun::STATUS_AWAITING_CONFLICT) {
            throw new BadRequestHttpException('Импорт не ожидает разрешения конфликта.');
        }

        if (!in_array($action, [
            FabricRegistryImportOptions::CONFLICT_SKIP,
            FabricRegistryImportOptions::CONFLICT_SKIP_ALL,
            FabricRegistryImportOptions::CONFLICT_UPDATE,
        ], true)) {
            throw new BadRequestHttpException('Недопустимое действие для конфликта.');
        }

        $options = $run->getOptions();
        if ($action === FabricRegistryImportOptions::CONFLICT_SKIP_ALL) {
            $options['conflict_resolution'] = FabricRegistryImportOptions::CONFLICT_SKIP;
            unset($options['conflict_resolution_once']);
        } else {
            $options['conflict_resolution'] = FabricRegistryImportOptions::CONFLICT_ABORT;
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
    public function getStatusPayload(int $runId): array
    {
        $run = $this->findRun($runId);
        $this->recoverFalseAwaitingConflictForSkipMode($run);
        $this->syncAwaitingConflictFromStats($run);
        $this->ensureRunCompleted($run);
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
            $mediaTotal = max($mediaTotal, $this->completion->pendingFabricMediaCount($runId));
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
            'reportUrl' => $run->isTerminal() || $run->status === CatalogImportRun::STATUS_AWAITING_CONFLICT
                ? '/admin/fabric-collection/import-report/' . $run->id
                : null,
            'finished' => $run->isTerminal()
                || $run->status === CatalogImportRun::STATUS_AWAITING_CONFLICT,
            'success' => $run->status === CatalogImportRun::STATUS_COMPLETED,
        ];
    }

    private function ensureRunCompleted(CatalogImportRun $run): void
    {
        $this->completion->finalizeCompleted($run);
    }

    /**
     * Старые прогоны: pending_conflict оставался в stats при авто-пропуске дубликатов.
     */
    private function recoverFalseAwaitingConflictForSkipMode(CatalogImportRun $run): void
    {
        if ($run->status !== CatalogImportRun::STATUS_AWAITING_CONFLICT) {
            return;
        }

        $payload = $run->getStats();
        if (!empty($payload['aborted'])) {
            return;
        }

        $runOptions = $run->getOptions();
        if (($runOptions['conflict_resolution'] ?? '') !== FabricRegistryImportOptions::CONFLICT_SKIP) {
            return;
        }

        $payload['pending_conflict'] = null;
        $run->setStats($payload);
        $run->status = CatalogImportRun::STATUS_QUEUED;
        $run->phase = CatalogImportRun::PHASE_QUEUED;
        $run->phase_message = 'Продолжение импорта…';
        $run->finished_at = null;
        if ((int)$run->resume_row_index <= 0 && (int)$run->processed_rows > 0) {
            $run->resume_row_index = (int)$run->processed_rows;
        }
        $run->save(false);
        $this->dispatchWorker((int)$run->id);
    }

    private function syncAwaitingConflictFromStats(CatalogImportRun $run): void
    {
        if ($run->status !== CatalogImportRun::STATUS_PROCESSING) {
            return;
        }

        $payload = $run->getStats();
        if (empty($payload['pending_conflict']) || !is_array($payload['pending_conflict'])) {
            return;
        }

        if (empty($payload['aborted'])) {
            return;
        }

        $run->status = CatalogImportRun::STATUS_AWAITING_CONFLICT;
        $run->phase_message = 'Конфликт: требуется решение в админке.';
        $run->finished_at = null;
        $run->save(false, ['status', 'phase_message', 'finished_at']);
    }

    public function dispatchWorker(int $runId): void
    {
        ImportRunWorkerLauncher::dispatch('fabric-import/run', $runId, 'fabric-import');
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
        if ($this->completion->pendingFabricMediaCount((int)$run->id) <= 0) {
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
            'type' => CatalogImportRun::TYPE_FABRIC,
        ]);
        if ($run === null) {
            throw new NotFoundHttpException('Импорт не найден.');
        }

        return $run;
    }

    private function lockPath(int $runId): string
    {
        $dir = Yii::getAlias('@runtime/fabric-import');
        FileHelper::createDirectory($dir);

        return $dir . '/run-' . $runId . '.lock';
    }

    /**
     * @return array<string, mixed>
     */
    private function optionsToArray(FabricRegistryImportOptions $options): array
    {
        return [
            'update_existing' => $options->updateExisting,
            'import_media' => $options->importMedia,
            'conflict_resolution' => $options->conflictResolution,
            'filename' => $options->filename,
            'import_source' => $options->importSource,
        ];
    }

    private function optionsFromRun(CatalogImportRun $run): FabricRegistryImportOptions
    {
        $data = $run->getOptions();
        $options = new FabricRegistryImportOptions();
        $options->updateExisting = (bool)($data['update_existing'] ?? false);
        $options->importMedia = (bool)($data['import_media'] ?? true);
        $options->conflictResolution = isset($data['conflict_resolution']) && $data['conflict_resolution'] !== ''
            ? (string)$data['conflict_resolution']
            : null;
        $options->conflictResolutionOnce = isset($data['conflict_resolution_once']) && $data['conflict_resolution_once'] !== ''
            ? (string)$data['conflict_resolution_once']
            : null;
        $options->filename = (string)($data['filename'] ?? $run->filename);
        $options->importSource = (string)($data['import_source'] ?? 'fabric_registry');
        $options->userId = $run->user_id !== null ? (int)$run->user_id : null;

        return $options;
    }

    private function buildResultFromRun(CatalogImportRun $run): FabricRegistryImportResult
    {
        $payload = $run->getStats();
        if ($payload === []) {
            return new FabricRegistryImportResult();
        }

        $result = new FabricRegistryImportResult();
        $result->success = (bool)($payload['success'] ?? true);
        $result->aborted = (bool)($payload['aborted'] ?? false);
        $savedStats = is_array($payload['stats'] ?? null) ? $payload['stats'] : [];
        $result->stats = array_merge($result->stats, $savedStats);
        $result->pendingConflict = is_array($payload['pending_conflict'] ?? null)
            ? $payload['pending_conflict']
            : null;
        $result->importRun = $run;

        foreach ((array)($payload['rows'] ?? []) as $rowData) {
            if (!is_array($rowData)) {
                continue;
            }
            $row = new FabricRegistryImportRowResult();
            $row->rowNumber = (int)($rowData['row_number'] ?? 0);
            $row->collectionName = (string)($rowData['collection'] ?? $rowData['collection_name'] ?? '');
            $row->designCode = (string)($rowData['design_code'] ?? '');
            $row->colorLabel = (string)($rowData['color'] ?? $rowData['color_label'] ?? '');
            $row->action = (string)($rowData['action'] ?? FabricRegistryImportRowResult::ACTION_SKIP);
            $row->photoImported = (bool)($rowData['photo_imported'] ?? false);
            $row->photoSkipped = (bool)($rowData['photo_skipped'] ?? false);
            $row->messages = is_array($rowData['messages'] ?? null) ? $rowData['messages'] : [];
            $result->addRow($row);
        }

        return $result;
    }
}
