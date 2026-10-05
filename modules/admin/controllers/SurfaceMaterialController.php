<?php

namespace app\modules\admin\controllers;

use app\models\CatalogCollection;
use app\models\CatalogImportRun;
use app\models\CatalogSurfaceMaterial;
use app\services\cache\ApiCacheInvalidator;
use app\services\import\surface\SurfaceMaterialImportRunService;
use app\services\import\surface\SurfaceMaterialRegistryImportOptions;
use app\services\import\surface\SurfaceMaterialRegistryImportResult;
use app\services\import\surface\SurfaceMaterialRegistryImportRowResult;
use app\modules\admin\models\SurfaceMaterialSearch;
use Yii;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\UploadedFile;

class SurfaceMaterialController extends BaseController
{
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $this->requirePermission('catalog');

        return true;
    }

    public function actionIndex(): string
    {
        $searchModel = new SurfaceMaterialSearch();
        $provider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'materials' => $provider->getModels(),
            'searchModel' => $searchModel,
        ]);
    }

    public function actionCreate(): Response|string
    {
        $model = new CatalogSurfaceMaterial([
            'is_active' => true,
            'sort_order' => 0,
            'material_type' => CatalogSurfaceMaterial::TYPE_WOOD,
        ]);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            $model->replaceCollectionLinks((array)Yii::$app->request->post('collection_ids', []));
            Yii::$app->session->setFlash('success', 'Материал создан.');
            ApiCacheInvalidator::touch();

            return $this->redirect(['index']);
        }

        return $this->render('form', [
            'model' => $model,
            'title' => 'Новый материал',
            'collections' => $this->catalogCollections(),
            'linkedCollectionIds' => [],
        ]);
    }

    public function actionUpdate(int $id): Response|string
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            $model->replaceCollectionLinks((array)Yii::$app->request->post('collection_ids', []));
            Yii::$app->session->setFlash('success', 'Материал обновлён.');
            ApiCacheInvalidator::touch();

            return $this->redirect(['index']);
        }

        return $this->render('form', [
            'model' => $model,
            'title' => 'Редактирование материала',
            'collections' => $this->catalogCollections(),
            'linkedCollectionIds' => $model->getLinkedCollectionIds(),
        ]);
    }

    public function actionDelete(int $id): Response
    {
        $model = $this->findModel($id);
        $model->delete();
        ApiCacheInvalidator::touch();
        Yii::$app->session->setFlash('success', 'Материал удалён.');

        return $this->redirect(['index']);
    }

    public function actionImportStart(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $uploadedFile = UploadedFile::getInstanceByName('registry_file');
        if ($uploadedFile === null) {
            Yii::$app->response->statusCode = 400;

            return ['message' => 'Выберите файл реестра (.xlsx).'];
        }

        $options = new SurfaceMaterialRegistryImportOptions();
        $this->applyPostedRegistryImportOptions($options);
        $options->userId = Yii::$app->user->id ?? null;
        $options->filename = (string)$uploadedFile->name;

        try {
            $service = new SurfaceMaterialImportRunService();
            $run = $service->startFromUpload($uploadedFile, $options, Yii::$app->user->id ?? null);

            return [
                'runId' => (int)$run->id,
                'status' => $run->status,
                'message' => $run->phase_message,
            ];
        } catch (\Throwable $e) {
            Yii::$app->response->statusCode = 400;

            return ['message' => $e->getMessage()];
        }
    }

    public function actionImportStatus(int $id): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        return (new SurfaceMaterialImportRunService())->getStatusPayload($id);
    }

    public function actionImportResolve(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $runId = (int)Yii::$app->request->post('run_id', 0);
        $action = (string)Yii::$app->request->post('conflict_action', '');

        if ($runId <= 0) {
            Yii::$app->response->statusCode = 400;

            return ['message' => 'Не указан run_id.'];
        }

        try {
            $service = new SurfaceMaterialImportRunService();
            $run = $service->resolveConflict($runId, $action);

            return $service->getStatusPayload((int)$run->id);
        } catch (\Throwable $e) {
            Yii::$app->response->statusCode = 400;

            return ['message' => $e->getMessage()];
        }
    }

    public function actionImportReport(int $id): string
    {
        $importRun = CatalogImportRun::findOne([
            'id' => $id,
            'type' => CatalogImportRun::TYPE_SURFACE_MATERIAL,
        ]);
        if ($importRun === null) {
            throw new NotFoundHttpException('Отчёт импорта не найден.');
        }

        $payload = $importRun->getStats();
        $result = $this->buildResultFromPayload(is_array($payload) ? $payload : []);

        return $this->render('import-report', [
            'title' => 'Отчёт импорта',
            'result' => $result,
            'importRun' => $importRun,
        ]);
    }

    private function applyPostedRegistryImportOptions(SurfaceMaterialRegistryImportOptions $options): void
    {
        $posted = (string)Yii::$app->request->post(
            'conflict_resolution',
            \app\modules\admin\helpers\RegistryImportPostedOptions::MODE_SKIP
        );
        $parsed = \app\modules\admin\helpers\RegistryImportPostedOptions::parseConflictResolution(
            $posted,
            SurfaceMaterialRegistryImportOptions::CONFLICT_SKIP,
            SurfaceMaterialRegistryImportOptions::CONFLICT_UPDATE
        );
        $options->conflictResolution = $parsed['conflictResolution'];
        $options->updateExisting = $parsed['conflictResolution'] === SurfaceMaterialRegistryImportOptions::CONFLICT_UPDATE;
        $options->importMedia = $parsed['importMedia'];
    }

    /**
     * @return CatalogCollection[]
     */
    private function catalogCollections(): array
    {
        return CatalogCollection::find()
            ->orderBy(['sort_order' => SORT_ASC, 'name' => SORT_ASC])
            ->all();
    }

    private function findModel(int $id): CatalogSurfaceMaterial
    {
        $model = CatalogSurfaceMaterial::findOne($id);
        if ($model === null) {
            throw new NotFoundHttpException('Материал не найден.');
        }

        return $model;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function buildResultFromPayload(array $payload): SurfaceMaterialRegistryImportResult
    {
        $result = new SurfaceMaterialRegistryImportResult();
        $result->success = (bool)($payload['success'] ?? true);
        $result->aborted = (bool)($payload['aborted'] ?? false);
        $result->stats = is_array($payload['stats'] ?? null) ? $payload['stats'] : $result->stats;
        $result->pendingConflict = is_array($payload['pending_conflict'] ?? null)
            ? $payload['pending_conflict']
            : null;

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
            $row->warnings = is_array($rowData['warnings'] ?? null) ? $rowData['warnings'] : [];
            $result->addRow($row);
        }

        return $result;
    }
}
