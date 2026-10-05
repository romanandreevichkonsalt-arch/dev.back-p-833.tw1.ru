<?php

namespace app\services\import\surface;

use app\models\CatalogImportRun;
use app\services\cache\ApiCacheInvalidator;
use app\services\import\ImportRunWorkerLauncher;
use app\services\import\SpreadsheetFormatValidator;
use Yii;
use yii\helpers\FileHelper;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;
use yii\web\UploadedFile;

class SurfaceMaterialImportRunService
{
    public function __construct(
        private readonly SurfaceMaterialRegistryImporter $importer = new SurfaceMaterialRegistryImporter(),
    ) {
    }

    public function startFromUpload(UploadedFile $file, SurfaceMaterialRegistryImportOptions $options, ?int $userId): CatalogImportRun
    {
        $extension = strtolower((string)$file->extension);
        if (!in_array($extension, ['xlsx', 'xls'], true)) {
            throw new BadRequestHttpException('Допустимы только файлы Excel (.xlsx, .xls).');
        }

        $importDir = Yii::getAlias('@runtime/surface-material-import');
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
            'type' => CatalogImportRun::TYPE_SURFACE_MATERIAL,
            'user_id' => $userId,
            'filename' => $options->filename !== '' ? $options->filename : (string)$file->name,
            'sheet' => SurfaceMaterialRegistrySpreadsheetReader::SHEET,
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
            SurfaceMaterialRegistryImportOptions::CONFLICT_SKIP,
            SurfaceMaterialRegistryImportOptions::CONFLICT_SKIP_ALL,
            SurfaceMaterialRegistryImportOptions::CONFLICT_UPDATE,
        ], true)) {
            throw new BadRequestHttpException('Недопустимое действие для конфликта.');
        }

        $options = $run->getOptions();
        if ($action === SurfaceMaterialRegistryImportOptions::CONFLICT_SKIP_ALL) {
            $options['conflict_resolution'] = SurfaceMaterialRegistryImportOptions::CONFLICT_SKIP;
            unset($options['conflict_resolution_once']);
        } else {
            $options['conflict_resolution'] = SurfaceMaterialRegistryImportOptions::CONFLICT_ABORT;
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
                ? '/admin/surface-material/import-report/' . $run->id
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
        ImportRunWorkerLauncher::dispatch('surface-material-import/run', $runId, 'surface-material-import');
    }

    private function findRun(int $runId): CatalogImportRun
    {
        $run = CatalogImportRun::findOne([
            'id' => $runId,
            'type' => CatalogImportRun::TYPE_SURFACE_MATERIAL,
        ]);
        if ($run === null) {
            throw new NotFoundHttpException('Импорт не найден.');
        }

        return $run;
    }

    private function lockPath(int $runId): string
    {
        $dir = Yii::getAlias('@runtime/surface-material-import');
        FileHelper::createDirectory($dir);

        return $dir . '/run-' . $runId . '.lock';
    }

    /**
     * @return array<string, mixed>
     */
    private function optionsToArray(SurfaceMaterialRegistryImportOptions $options): array
    {
        return [
            'update_existing' => $options->updateExisting,
            'import_media' => $options->importMedia,
            'conflict_resolution' => $options->conflictResolution,
            'filename' => $options->filename,
            'import_source' => $options->importSource,
        ];
    }

    private function optionsFromRun(CatalogImportRun $run): SurfaceMaterialRegistryImportOptions
    {
        $data = $run->getOptions();
        $options = new SurfaceMaterialRegistryImportOptions();
        $options->updateExisting = (bool)($data['update_existing'] ?? false);
        $options->importMedia = (bool)($data['import_media'] ?? true);
        $options->conflictResolution = isset($data['conflict_resolution']) && $data['conflict_resolution'] !== ''
            ? (string)$data['conflict_resolution']
            : null;
        $options->conflictResolutionOnce = isset($data['conflict_resolution_once']) && $data['conflict_resolution_once'] !== ''
            ? (string)$data['conflict_resolution_once']
            : null;
        $options->filename = (string)($data['filename'] ?? $run->filename);
        $options->importSource = (string)($data['import_source'] ?? 'surface_material_registry');
        $options->userId = $run->user_id !== null ? (int)$run->user_id : null;

        return $options;
    }

    private function buildResultFromRun(CatalogImportRun $run): SurfaceMaterialRegistryImportResult
    {
        $payload = $run->getStats();
        if ($payload === []) {
            return new SurfaceMaterialRegistryImportResult();
        }

        $result = new SurfaceMaterialRegistryImportResult();
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
            $row = new SurfaceMaterialRegistryImportRowResult();
            $row->rowNumber = (int)($rowData['row_number'] ?? 0);
            $row->materialType = (string)($rowData['material_type'] ?? $rowData['collection'] ?? '');
            $row->name = (string)($rowData['name'] ?? $rowData['design_code'] ?? '');
            $row->action = (string)($rowData['action'] ?? SurfaceMaterialRegistryImportRowResult::ACTION_SKIP);
            $row->photoImported = (bool)($rowData['photo_imported'] ?? false);
            $row->photoSkipped = (bool)($rowData['photo_skipped'] ?? false);
            $row->textureImported = (bool)($rowData['texture_imported'] ?? false);
            $row->textureSkipped = (bool)($rowData['texture_skipped'] ?? false);
            $row->messages = is_array($rowData['messages'] ?? null) ? $rowData['messages'] : [];
            $result->addRow($row);
        }

        return $result;
    }
}
