<?php

namespace app\modules\admin\controllers;

use app\models\CatalogFabricCollection;
use app\models\CatalogFabricColor;
use app\models\CatalogModel;
use app\models\CatalogPriceCategory;
use app\services\catalog\CatalogModelProductSyncService;
use app\services\catalog\FabricLibraryArchiveLauncher;
use app\services\catalog\FabricLibraryArchiveUrls;
use app\services\cache\ApiCacheInvalidator;
use app\services\import\SpreadsheetFormatValidator;
use app\services\import\fabric\FabricDesignCodeNormalizer;
use app\services\import\fabric\FabricImportRunService;
use app\services\import\fabric\FabricRegistryImportOptions;
use app\services\import\fabric\FabricRegistryImporter;
use app\services\import\fabric\FabricRegistryImportResult;
use app\services\import\fabric\FabricRegistryImportRowResult;
use app\modules\admin\models\FabricCollectionSearch;
use Yii;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\ServerErrorHttpException;
use yii\web\UploadedFile;

class FabricCollectionController extends BaseController
{
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $this->requirePermission('catalog');

        return true;
    }

    public const TAB_COLLECTIONS = 'collections';
    public const TAB_CATEGORIES = 'categories';

    public function actionIndex(): string
    {
        $activeTab = (string)Yii::$app->request->get('tab', self::TAB_COLLECTIONS);
        if (!in_array($activeTab, [self::TAB_COLLECTIONS, self::TAB_CATEGORIES], true)) {
            $activeTab = self::TAB_COLLECTIONS;
        }

        $searchModel = new FabricCollectionSearch();
        $collectionsProvider = $searchModel->search(Yii::$app->request->queryParams);

        $priceCategories = CatalogPriceCategory::findFabricOrdered();

        return $this->render('index', [
            'collections' => $collectionsProvider->getModels(),
            'searchModel' => $searchModel,
            'priceCategories' => $priceCategories,
            'activeTab' => $activeTab,
        ]);
    }

    public function actionDownloadTexturesArchive(): Response
    {
        if (!FabricLibraryArchiveUrls::exists()) {
            $build = FabricLibraryArchiveLauncher::rebuildNow();
            if (!($build['success'] ?? false) && !FabricLibraryArchiveUrls::exists()) {
                throw new ServerErrorHttpException(
                    'Не удалось собрать PDF-каталог: '
                    . ($build['message'] ?? 'файл не создан. На сервере выполните composer install в каталоге приложения.')
                );
            }
        }

        return Yii::$app->response->sendFile(
            FabricLibraryArchiveUrls::absolutePath(),
            basename(FabricLibraryArchiveUrls::relativePath()),
            [
                'mimeType' => 'application/pdf',
                'inline' => false,
            ]
        );
    }

    public function actionCreate(): Response|string
    {
        $model = new CatalogFabricCollection([
            'is_active' => true,
            'sort_order' => 0,
            'material_kind' => CatalogFabricCollection::MATERIAL_KIND_FABRIC,
        ]);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            Yii::$app->session->setFlash('success', 'Коллекция создана. Добавьте цвета.');
            ApiCacheInvalidator::touch();
            return $this->redirect(['update', 'id' => $model->id]);
        }

        return $this->render('form', array_merge($this->getFormViewParams($model), [
            'model' => $model,
            'title' => 'Новая коллекция',
        ]));
    }

    public function actionUpdate(int $id): Response|string
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            $errors = $this->syncColorLinks($model, (array)Yii::$app->request->post('fabric_color_links', []));
            Yii::$container->get(CatalogModelProductSyncService::class)
                ->syncForFabricCollectionId((int)$model->id);

            if ($errors !== []) {
                Yii::$app->session->setFlash('error', implode(' ', $errors));
                return $this->render('form', array_merge($this->getFormViewParams($model), [
                    'model' => $model,
                    'title' => 'Редактирование коллекции',
                ]));
            }

            Yii::$app->session->setFlash('success', 'Коллекция обновлена.');
            ApiCacheInvalidator::touch();
            return $this->redirect(['index', 'tab' => self::TAB_COLLECTIONS]);
        }

        $model = CatalogFabricCollection::find()
            ->where(['id' => $id])
            ->one() ?? $model;

        return $this->render('form', array_merge($this->getFormViewParams($model), [
            'model' => $model,
            'title' => 'Редактирование коллекции',
        ]));
    }

    public function actionDelete(int $id): Response
    {
        $model = $this->findModel($id);
        $syncService = Yii::$container->get(CatalogModelProductSyncService::class);

        $colorIds = CatalogFabricColor::find()
            ->select('id')
            ->where(['fabric_collection_id' => $model->id])
            ->column();
        $modelIds = Yii::$app->db->createCommand(
            'SELECT model_id FROM {{%catalog_model_fabric_collections}} WHERE fabric_collection_id = :fc',
            ['fc' => (int)$model->id]
        )->queryColumn();

        $deletedProducts = $syncService->deleteProductsForFabricColorIds($colorIds);
        $model->delete();

        foreach ($modelIds as $modelId) {
            $catalogModel = CatalogModel::findOne((int)$modelId);
            if ($catalogModel !== null) {
                $syncService->syncForModel($catalogModel);
            }
        }

        ApiCacheInvalidator::touch();
        $message = 'Коллекция удалена.';
        if ($deletedProducts > 0) {
            $message .= ' Удалено товаров (SKU): ' . $deletedProducts . '.';
        }
        Yii::$app->session->setFlash('success', $message);

        return $this->redirect(['index']);
    }

    public function actionSavePriceCategories(): Response
    {
        $rows = (array)Yii::$app->request->post('price_categories', []);
        $saved = 0;

        foreach ($rows as $id => $row) {
            if (!is_array($row)) {
                continue;
            }
            $category = CatalogPriceCategory::findOne((int)$id);
            if ($category === null) {
                continue;
            }
            $category->label = trim((string)($row['label'] ?? $category->label));
            $category->price_min = $this->nullableInt($row['price_min'] ?? null);
            $category->price_max = $this->nullableInt($row['price_max'] ?? null);
            $category->label_line1 = trim((string)($row['label_line1'] ?? $category->label_line1 ?? ''));
            $category->price_min_line1 = $this->nullableInt($row['price_min_line1'] ?? null);
            $category->price_max_line1 = $this->nullableInt($row['price_max_line1'] ?? null);
            if ($category->label_line1 === '') {
                $category->label_line1 = null;
            }
            if ($category->save()) {
                $saved++;
            }
        }

        Yii::$app->session->setFlash('success', $saved > 0 ? 'Категории ткани обновлены.' : 'Нет изменений.');

        return $this->redirect(['index', 'tab' => self::TAB_CATEGORIES]);
    }

    public function actionImportStart(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $uploadedFile = UploadedFile::getInstanceByName('registry_file');
        if ($uploadedFile === null) {
            Yii::$app->response->statusCode = 400;

            return ['message' => 'Выберите файл реестра (.xlsx).'];
        }

        $options = new FabricRegistryImportOptions();
        $this->applyPostedRegistryImportOptions($options);
        $options->userId = Yii::$app->user->id ?? null;
        $options->filename = (string)$uploadedFile->name;

        try {
            $service = new FabricImportRunService();
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

        $service = new FabricImportRunService();

        return $service->getStatusPayload($id);
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
            $service = new FabricImportRunService();
            $run = $service->resolveConflict($runId, $action);

            return $service->getStatusPayload((int)$run->id);
        } catch (\Throwable $e) {
            Yii::$app->response->statusCode = 400;

            return ['message' => $e->getMessage()];
        }
    }

    /** @deprecated Используйте import-start + polling */
    public function actionImportRun(): Response|string
    {
        $uploadedFile = \yii\web\UploadedFile::getInstanceByName('registry_file');
        if ($uploadedFile === null) {
            Yii::$app->session->setFlash('error', 'Выберите файл реестра (.xlsx).');

            return $this->redirect(['index', 'tab' => self::TAB_COLLECTIONS]);
        }

        $extension = strtolower((string)$uploadedFile->extension);
        if (!in_array($extension, ['xlsx', 'xls'], true)) {
            Yii::$app->session->setFlash('error', 'Допустимы только файлы Excel (.xlsx, .xls).');

            return $this->redirect(['index', 'tab' => self::TAB_COLLECTIONS]);
        }

        $importDir = Yii::getAlias('@runtime/fabric-import');
        \yii\helpers\FileHelper::createDirectory($importDir);
        $storedPath = $importDir . '/' . uniqid('registry_', true) . '.' . $extension;
        if (!$uploadedFile->saveAs($storedPath)) {
            Yii::$app->session->setFlash('error', 'Не удалось сохранить загруженный файл.');

            return $this->redirect(['index', 'tab' => self::TAB_COLLECTIONS]);
        }

        try {
            SpreadsheetFormatValidator::assertReadableExcel($storedPath);
        } catch (\InvalidArgumentException $e) {
            @unlink($storedPath);
            Yii::$app->session->setFlash('error', 'Ошибка импорта: ' . $e->getMessage());

            return $this->redirect(['index', 'tab' => self::TAB_COLLECTIONS]);
        }

        $options = new FabricRegistryImportOptions();
        $this->applyPostedRegistryImportOptions($options);
        $options->userId = Yii::$app->user->id ?? null;
        $options->filename = $uploadedFile->name;

        try {
            $importer = new FabricRegistryImporter();
            $result = $importer->import($storedPath, $options);
        } catch (\Throwable $e) {
            @unlink($storedPath);
            Yii::$app->session->setFlash('error', 'Ошибка импорта: ' . $e->getMessage());

            return $this->redirect(['index', 'tab' => self::TAB_COLLECTIONS]);
        }

        @unlink($storedPath);

        if ($result->aborted && $result->pendingConflict !== null) {
            Yii::$app->session->set('fabric_import_conflict', [
                'result' => $result->toArray(),
                'options' => [
                    'update_existing' => $options->updateExisting,
                    'import_media' => $options->importMedia,
                    'filename' => $options->filename,
                ],
            ]);

            return $this->render('import-conflict', [
                'conflict' => $result->pendingConflict,
                'result' => $result,
            ]);
        }

        if ($result->importRun === null) {
            Yii::$app->session->setFlash('error', 'Импорт не создал отчёт.');

            return $this->redirect(['index', 'tab' => self::TAB_COLLECTIONS]);
        }

        $result->importRun->setStats($result->toArray());
        $result->importRun->status = $result->success ? 'completed' : 'failed';
        $result->importRun->save(false);

        $flashKey = $result->success && ($result->stats['errors'] ?? 0) === 0 ? 'success' : 'warning';
        Yii::$app->session->setFlash($flashKey, $this->buildImportFlashMessage($result));
        ApiCacheInvalidator::touch();

        return $this->redirect(['index', 'tab' => self::TAB_COLLECTIONS]);
    }

    public function actionImportReport(int $id): string
    {
        $importRun = \app\models\CatalogImportRun::findOne($id);
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

    /**
     * @param array<string, mixed> $payload
     */
    private function buildResultFromPayload(array $payload): FabricRegistryImportResult
    {
        $result = new FabricRegistryImportResult();
        $result->success = (bool)($payload['success'] ?? true);
        $result->aborted = (bool)($payload['aborted'] ?? false);
        $result->stats = is_array($payload['stats'] ?? null) ? $payload['stats'] : $result->stats;
        $result->pendingConflict = is_array($payload['pending_conflict'] ?? null) ? $payload['pending_conflict'] : null;

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

    private function buildImportFlashMessage(FabricRegistryImportResult $result): string
    {
        $stats = $result->stats;
        $parts = [
            'создано: ' . (int)($stats['links_created'] ?? 0),
            'обновлено: ' . (int)($stats['links_updated'] ?? 0),
        ];

        if (($stats['rows_skipped'] ?? 0) > 0) {
            $parts[] = 'пропущено: ' . (int)$stats['rows_skipped'];
        }
        if (($stats['photo_imported'] ?? 0) > 0) {
            $parts[] = 'фото загружено: ' . (int)$stats['photo_imported'];
        }
        if (($stats['errors'] ?? 0) > 0) {
            $parts[] = 'ошибок: ' . (int)$stats['errors'];
        }

        return 'Импорт завершён. ' . implode(', ', $parts) . '.';
    }

    private function applyPostedRegistryImportOptions(FabricRegistryImportOptions $options): void
    {
        $posted = (string)Yii::$app->request->post(
            'conflict_resolution',
            \app\modules\admin\helpers\RegistryImportPostedOptions::MODE_SKIP
        );
        $parsed = \app\modules\admin\helpers\RegistryImportPostedOptions::parseConflictResolution(
            $posted,
            FabricRegistryImportOptions::CONFLICT_SKIP,
            FabricRegistryImportOptions::CONFLICT_UPDATE
        );
        $options->conflictResolution = $parsed['conflictResolution'];
        $options->updateExisting = $parsed['conflictResolution'] === FabricRegistryImportOptions::CONFLICT_UPDATE;
        $options->importMedia = $parsed['importMedia'];
    }

    /**
     * @param array<int|string, array<string, mixed>> $rows
     * @return string[]
     */
    private function syncColorLinks(CatalogFabricCollection $collection, array $rows): array
    {
        $existing = CatalogFabricColor::find()
            ->where(['fabric_collection_id' => $collection->id])
            ->indexBy('id')
            ->all();

        $keptIds = [];
        $sortOrder = 0;
        $errors = [];
        $usedCodes = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $designCode = FabricDesignCodeNormalizer::fromRegistry((string)($row['design_code'] ?? ''));
            if ($designCode === '') {
                continue;
            }

            if (isset($usedCodes[$designCode])) {
                $errors[] = 'Дублируется название «' . $designCode . '» в форме.';
                continue;
            }
            $usedCodes[$designCode] = true;

            $linkId = (int)($row['id'] ?? 0);
            $link = ($linkId > 0 && isset($existing[$linkId]))
                ? $existing[$linkId]
                : new CatalogFabricColor(['fabric_collection_id' => (int)$collection->id]);

            $duplicate = CatalogFabricColor::find()
                ->where([
                    'fabric_collection_id' => $collection->id,
                    'design_code' => $designCode,
                ])
                ->andFilterWhere(['not', ['id' => $link->isNewRecord ? null : $link->id]])
                ->one();

            if ($duplicate !== null) {
                $errors[] = 'Название «' . $designCode . '» уже есть в этой коллекции.';
                continue;
            }

            $colorId = (int)($row['color_id'] ?? 0);
            $link->color_id = $colorId > 0 ? $colorId : null;
            $link->design_code = $designCode;
            $link->swatch_media_id = $this->nullableInt($row['swatch_media_id'] ?? null);
            $link->sort_order = $sortOrder++;
            $link->is_active = (bool)($row['is_active'] ?? true);
            $link->is_recommended_fabric = filter_var($row['is_recommended_fabric'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $link->position_number = $this->nullableInt($row['position_number'] ?? null);

            if (!$link->save()) {
                $errors[] = 'Не удалось сохранить цвет «' . $designCode . '».';
                continue;
            }

            $keptIds[] = (int)$link->id;
        }

        foreach ($existing as $id => $link) {
            if (!in_array((int)$id, $keptIds, true)) {
                $link->delete();
            }
        }

        return $errors;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int)$value;
    }

    private function findModel(int $id): CatalogFabricCollection
    {
        $model = CatalogFabricCollection::findOne($id);
        if ($model === null) {
            throw new NotFoundHttpException('Коллекция не найдена.');
        }

        return $model;
    }

    /**
     * @return array<string, mixed>
     */
    private function getFormViewParams(?CatalogFabricCollection $model = null): array
    {
        $optionsAplus = ['' => '—'];
        $optionsLine1 = ['' => '—'];
        foreach (CatalogPriceCategory::findFabricOrdered(true) as $category) {
            $optionsAplus[$category->id] = $category->getDisplayLabel();
            $optionsLine1[$category->id] = $category->getDisplayLabelLine1();
        }

        return [
            'priceCategoryOptions' => $optionsAplus,
            'priceCategoryLine1Options' => $optionsLine1,
            'materialKindOptions' => CatalogFabricCollection::materialKindOptions(),
        ];
    }
}
