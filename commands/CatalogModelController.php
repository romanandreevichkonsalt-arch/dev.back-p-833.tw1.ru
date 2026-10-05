<?php

namespace app\commands;

use app\exceptions\ApiValidationException;
use app\models\CatalogModel;
use app\services\catalog\CatalogModel3dFileUploadService;
use app\services\catalog\CatalogModelProductSyncService;
use app\services\import\catalog\CatalogModelImporter;
use app\services\import\catalog\CatalogModelImportOptions;
use yii\console\Controller;
use yii\console\ExitCode;

class CatalogModelController extends Controller
{
    public bool $dryRun = false;
    public bool $update = false;

    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), ['dryRun', 'update']);
    }

    public function optionAliases(): array
    {
        return [
            'd' => 'dryRun',
            'u' => 'update',
        ];
    }

    public function actionImport(string $file): int
    {
        $options = new CatalogModelImportOptions();
        $options->dryRun = $this->dryRun;
        $options->updateExisting = $this->update;
        $options->conflictResolution = $this->update
            ? CatalogModelImportOptions::CONFLICT_UPDATE
            : CatalogModelImportOptions::CONFLICT_SKIP;
        $options->filename = basename($file);

        $importer = new CatalogModelImporter();
        try {
            $result = $importer->import($file, $options);
        } catch (\Throwable $exception) {
            $this->stderr('Import failed: ' . $exception->getMessage() . "\n");

            return ExitCode::UNSPECIFIED_ERROR;
        }

        $stats = $result->stats;
        $this->stdout(sprintf(
            "Import finished: created=%d updated=%d skipped=%d conflicts=%d errors=%d aborted=%s\n",
            $stats['models_created'] ?? 0,
            $stats['models_updated'] ?? 0,
            $stats['models_skipped'] ?? 0,
            $stats['models_conflict'] ?? 0,
            $stats['errors'] ?? 0,
            $result->aborted ? 'yes' : 'no'
        ));

        foreach ($result->rows as $row) {
            $this->stdout(sprintf(
                "  row %d [%s] %s / %s: %s\n",
                $row->rowNumber,
                $row->action,
                $row->collectionName,
                $row->modelLabel,
                implode('; ', $row->messages)
            ));
        }

        if ($result->aborted || ($stats['errors'] ?? 0) > 0) {
            return ExitCode::UNSPECIFIED_ERROR;
        }

        return ExitCode::OK;
    }

    /**
     * Скачивает файлы 3D в медиатеку для моделей, у которых в БД осталась только внешняя ссылка.
     */
    public function actionMaterialize3dFiles(): int
    {
        $models = CatalogModel::find()
            ->where([
                'or',
                ['file_3d_id' => null],
                ['file_3d_id' => 0],
            ])
            ->andWhere(['not', ['file_3d_url' => null]])
            ->andWhere(['<>', 'file_3d_url', ''])
            ->andWhere(['like', 'file_3d_url', 'http%', false])
            ->orderBy(['id' => SORT_ASC])
            ->all();

        if ($models === []) {
            $this->stdout("Нет моделей с внешней ссылкой 3D без сохранённого файла.\n");

            return ExitCode::OK;
        }

        $service = new CatalogModel3dFileUploadService();
        $saved = 0;
        $failed = 0;

        foreach ($models as $model) {
            /** @var CatalogModel $model */
            try {
                $service->uploadFromUrl($model);
                $saved++;
                $this->stdout(sprintf("OK #%d %s\n", (int)$model->id, $model->slug));
            } catch (ApiValidationException $exception) {
                $failed++;
                $this->stderr(sprintf(
                    "FAIL #%d %s: %s\n",
                    (int)$model->id,
                    $model->slug,
                    $exception->getMessage()
                ));
            }
        }

        $this->stdout(sprintf("Готово: сохранено %d, ошибок %d.\n", $saved, $failed));

        return $failed > 0 ? ExitCode::UNSPECIFIED_ERROR : ExitCode::OK;
    }

    public function actionSyncProducts(): int
    {
        $syncService = new CatalogModelProductSyncService();
        $models = \app\models\CatalogModel::find()->orderBy(['id' => SORT_ASC])->all();
        $count = 0;

        foreach ($models as $model) {
            $model->applyTitleSlug();
            $model->save(false);
            $syncService->syncForModel($model);
            $count++;
        }

        $this->stdout("Synced products for {$count} models.\n");

        return ExitCode::OK;
    }
}
