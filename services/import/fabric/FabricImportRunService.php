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

            $this->ensureRunCompleted($run);

            if ($run->status === CatalogImportRun::STATUS_COMPLETED) {
                @unlink($run->file_path);
                $run->file_path = null;
                $run->save(false, ['file_path']);
                ApiCacheInvalidator::touch();
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
            $run = $this->findRun($runId);
            $this->ensureRunCompleted($run);

            if ($run->status === CatalogImportRun::STATUS_COMPLETED) {
                $archiveResult = FabricLibraryArchiveLauncher::rebuildNow();
                if (!($archiveResult['success'] ?? false)) {
                    Yii::warning(
                        'Не удалось собрать архив текстур после импорта: ' . ($archiveResult['message'] ?? ''),
                        __METHOD__
                    );
                }
            }

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
        $this->ensureRunCompleted($run);

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

        return [
            'id' => (int)$run->id,
            'status' => $run->status,
            'phase' => $run->phase,
            'processed' => $processed,
            'total' => $total,
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
        if ($run->total_rows <= 0 || $run->processed_rows < $run->total_rows) {
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
        $run->save(false);
    }

    public function dispatchWorker(int $runId): void
    {
        ImportRunWorkerLauncher::dispatch('fabric-import/run', $runId, 'fabric-import');
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
        $result->stats = is_array($payload['stats'] ?? null) ? $payload['stats'] : $result->stats;
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
