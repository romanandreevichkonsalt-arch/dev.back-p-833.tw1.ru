<?php

namespace app\modules\admin\controllers;

use app\models\CatalogListingTileSettings;
use app\models\MediaFile;
use app\models\MediaFolder;
use app\services\cache\ApiCacheInvalidator;
use app\services\media\ListingFrameData;
use app\services\media\ListingTileService;
use app\services\media\LocalMediaStorage;
use app\services\media\MediaContentValidator;
use Yii;
use yii\data\ActiveDataProvider;
use yii\helpers\Url;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\UploadedFile;

class MediaController extends BaseController
{
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $listingTileActions = [
            'listing-tile-config',
            'listing-tile-load',
            'listing-tile-save',
            'listing-tile-floor-guide-save',
        ];
        if (in_array($action->id, $listingTileActions, true)) {
            $this->requireCatalogOrMediaPermission();

            return true;
        }

        $this->requirePermission('media');

        return true;
    }

    protected function requireCatalogOrMediaPermission(): void
    {
        /** @var \app\models\AdminUser|null $user */
        $user = Yii::$app->adminUser->identity;
        if ($user === null || (!$user->canAccess('media') && !$user->canAccess('catalog'))) {
            throw new \yii\web\ForbiddenHttpException('Недостаточно прав для этого раздела.');
        }
    }

    public function actionIndex(): string
    {
        $kind = $this->normalizeKindFilter((string)Yii::$app->request->get('kind', MediaFile::KIND_IMAGE));
        $folderId = (int)Yii::$app->request->get('folder_id', 0);

        $query = MediaFile::find()->with('folder')->orderBy(['id' => SORT_DESC]);
        if ($kind !== '') {
            $query->andWhere(['kind' => $kind]);
        }
        if ($folderId > 0) {
            $query->andWhere(['folder_id' => $folderId]);
        }

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => ['pageSize' => 24],
        ]);

        $listingTileConfig = (new ListingTileService())->getConfig();

        return $this->render('index', [
            'dataProvider' => $dataProvider,
            'activeKind' => $kind,
            'activeFolderId' => $folderId,
            'folders' => MediaFolder::find()->orderBy(['sort_order' => SORT_ASC])->all(),
            'listingTileConfig' => $listingTileConfig,
            'listingTileFloorGuide' => CatalogListingTileSettings::getSingleton()->floor_guide_from_bottom,
        ]);
    }

    public function actionSaveListingTileFloorGuide(): Response
    {
        $value = (int)Yii::$app->request->post('floor_guide_from_bottom', -1);
        $service = new ListingTileService();
        $tileHeight = $service->getConfig()->height;

        if ($value < 0 || $value >= $tileHeight) {
            Yii::$app->session->setFlash(
                'error',
                'Укажите высоту линии от 0 до ' . max(0, $tileHeight - 1) . ' px.'
            );

            return $this->redirect(['index', 'kind' => Yii::$app->request->get('kind', MediaFile::KIND_IMAGE)]);
        }

        $settings = CatalogListingTileSettings::getSingleton();
        if (!$settings->saveFloorGuideFromBottom($value, $tileHeight)) {
            Yii::$app->session->setFlash('error', 'Не удалось сохранить настройку линии опоры.');

            return $this->redirect(['index']);
        }

        Yii::$app->session->setFlash('success', 'Линия опоры для кадра каталога сохранена.');
        ApiCacheInvalidator::touch();

        return $this->redirect(['index', 'kind' => Yii::$app->request->get('kind', MediaFile::KIND_IMAGE)]);
    }

    public function actionUpload(): Response|string
    {
        $kind = $this->normalizeKindFilter((string)Yii::$app->request->get('kind', MediaFile::KIND_IMAGE));
        if ($kind === '') {
            $kind = MediaFile::KIND_IMAGE;
        }
        $folderId = (int)Yii::$app->request->get('folder_id', 0);

        if (Yii::$app->request->isPost) {
            $uploadedFile = UploadedFile::getInstanceByName('file');
            if ($uploadedFile === null) {
                Yii::$app->session->setFlash('error', 'Выберите файл для загрузки.');
            } else {
                try {
                    $storage = new LocalMediaStorage();
                    $alt = trim((string)Yii::$app->request->post('alt', ''));
                    $postKind = MediaFile::normalizeKind((string)Yii::$app->request->post('kind', $kind));
                    $postFolderId = (int)Yii::$app->request->post('folder_id', $folderId);
                    $folderSlug = trim((string)Yii::$app->request->post('folder', ''));
                    $media = $storage->upload(
                        $uploadedFile,
                        $alt !== '' ? $alt : null,
                        $postKind,
                        $postFolderId > 0 ? $postFolderId : null,
                        $folderSlug !== '' ? $folderSlug : null
                    );
                    Yii::$app->session->setFlash('success', 'Файл «' . $media->filename . '» загружен.');
                    ApiCacheInvalidator::touch();
                    return $this->redirect([
                        'index',
                        'kind' => $postKind,
                        'folder_id' => $media->folder_id ?? $postFolderId,
                    ]);
                } catch (\Throwable $e) {
                    Yii::$app->session->setFlash('error', $e->getMessage());
                }
            }
        }

        return $this->render('upload', [
            'kind' => $kind,
            'folderId' => $folderId,
            'folders' => MediaFolder::find()->orderBy(['sort_order' => SORT_ASC])->all(),
        ]);
    }

    public function actionUpdate(int $id): Response|string
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            ApiCacheInvalidator::touch();
            Yii::$app->session->setFlash('success', 'Метаданные обновлены.');
            return $this->redirect(['index', 'kind' => $model->kind, 'folder_id' => $model->folder_id ?? 0]);
        }

        return $this->render('update', [
            'model' => $model,
            'folders' => MediaFolder::find()->orderBy(['sort_order' => SORT_ASC])->all(),
        ]);
    }

    public function actionDelete(int $id): Response
    {
        $model = $this->findModel($id);
        $kind = $model->kind;
        $folderId = $model->folder_id;
        (new LocalMediaStorage())->delete($model);
        ApiCacheInvalidator::touch();
        Yii::$app->session->setFlash('success', 'Файл удалён.');

        return $this->redirect(['index', 'kind' => $kind, 'folder_id' => $folderId ?? 0]);
    }

    public function actionQuickUpload(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $uploadedFile = UploadedFile::getInstanceByName('file');
        if ($uploadedFile === null) {
            Yii::$app->response->statusCode = 400;

            return ['message' => 'Выберите файл для загрузки.'];
        }

        try {
            $storage = new LocalMediaStorage();
            $alt = trim((string)Yii::$app->request->post('alt', ''));
            $kind = MediaFile::normalizeKind((string)Yii::$app->request->post('kind', MediaFile::KIND_IMAGE));
            $folderId = (int)Yii::$app->request->post('folder_id', 0);
            $folderSlug = trim((string)Yii::$app->request->post('folder', ''));
            $media = $storage->upload(
                $uploadedFile,
                $alt !== '' ? $alt : null,
                $kind,
                $folderId > 0 ? $folderId : null,
                $folderSlug !== '' ? $folderSlug : null
            );

            ApiCacheInvalidator::touch();

            return $this->formatMediaItem($media);
        } catch (\Throwable $e) {
            Yii::$app->response->statusCode = 400;

            return ['message' => $e->getMessage()];
        }
    }

    public function actionQuickDelete(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $id = (int)Yii::$app->request->post('id', 0);
        if ($id <= 0) {
            Yii::$app->response->statusCode = 400;

            return ['message' => 'Не указан файл для удаления.'];
        }

        try {
            $model = $this->findModel($id);
            (new LocalMediaStorage())->delete($model);
            ApiCacheInvalidator::touch();

            return ['success' => true];
        } catch (\yii\db\IntegrityException $e) {
            Yii::$app->response->statusCode = 400;

            return ['message' => 'Файл используется в каталоге и не может быть удалён.'];
        } catch (\Throwable $e) {
            Yii::$app->response->statusCode = 400;

            return ['message' => $e->getMessage()];
        }
    }

    public function actionListingTileConfig(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $service = new ListingTileService();

        return $this->listingTileEditorBootstrap($service);
    }

    public function actionListingTileLoad(int $id): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $media = $this->findModel($id);
        if (!$media->isImage()) {
            Yii::$app->response->statusCode = 400;

            return ['message' => 'Кадр каталога доступен только для изображений.'];
        }

        $service = new ListingTileService();
        $tileConfig = $service->getConfig();
        $savedFrame = $service->decodeFrameJson($media->listing_frame_json);
        if ($savedFrame !== null) {
            $frame = ListingFrameData::fromArray($savedFrame) ?? ListingFrameData::defaults();
        } else {
            $frame = ListingFrameData::defaultsForImage(
                (int)$media->width,
                (int)$media->height,
                $tileConfig->width,
                $tileConfig->height,
            );
        }
        $previewVariant = MediaContentValidator::resolveReadableVariant($media, 'medium') ?? 'original';

        return array_merge(
            $this->listingTileEditorBootstrap($service),
            [
                'mediaId' => (int)$media->id,
                'originalUrl' => $media->getPublicUrl('original'),
                'previewUrl' => $media->getPublicUrl($previewVariant),
                'locked' => $media->isListingFrameLocked(),
                'frame' => $frame->toArray(),
            ],
        );
    }

    public function actionListingTileFloorGuideSave(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        if (!$this->canEditListingTileFloorGuide()) {
            Yii::$app->response->statusCode = 403;

            return ['message' => 'Недостаточно прав для изменения линии опоры.'];
        }

        if (!Yii::$app->request->isPost) {
            Yii::$app->response->statusCode = 405;

            return ['message' => 'Метод не поддерживается.'];
        }

        $value = (int)Yii::$app->request->post('floorGuideFromBottom', -1);
        $service = new ListingTileService();
        $tileHeight = $service->getConfig()->height;
        if ($value < 0 || $value >= $tileHeight) {
            Yii::$app->response->statusCode = 400;

            return ['message' => 'Укажите высоту линии от 0 до ' . max(0, $tileHeight - 1) . ' px.'];
        }

        $settings = CatalogListingTileSettings::getSingleton();
        if (!$settings->saveFloorGuideFromBottom($value, $tileHeight)) {
            Yii::$app->response->statusCode = 400;

            return ['message' => 'Не удалось сохранить настройку.'];
        }

        ApiCacheInvalidator::touch();
        $config = (new ListingTileService())->getConfig();

        return [
            'success' => true,
            'config' => $config->toEditorPayload(),
        ];
    }

    public function actionListingTileSave(int $id): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        if (!Yii::$app->request->isPost) {
            Yii::$app->response->statusCode = 405;

            return ['message' => 'Метод не поддерживается.'];
        }

        $media = $this->findModel($id);
        if (!$media->isImage()) {
            Yii::$app->response->statusCode = 400;

            return ['message' => 'Кадр каталога доступен только для изображений.'];
        }

        try {
            $frame = ListingFrameData::fromRequest(Yii::$app->request->post());
            $service = new ListingTileService();
            $media = $service->saveFrame($media, $frame);
            ApiCacheInvalidator::touch();

            $previewVariant = MediaContentValidator::resolveReadableVariant($media, 'medium') ?? 'original';

            return [
                'success' => true,
                'previewUrl' => $media->getPublicUrl($previewVariant),
                'locked' => $media->isListingFrameLocked(),
                'frame' => $frame->toArray(),
            ];
        } catch (\Throwable $e) {
            Yii::$app->response->statusCode = 400;

            return ['message' => $e->getMessage()];
        }
    }

    public function actionFoldersList(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $folders = MediaFolder::find()->orderBy(['sort_order' => SORT_ASC])->all();
        $items = [];
        foreach ($folders as $folder) {
            $items[] = [
                'id' => (int)$folder->id,
                'slug' => $folder->slug,
                'label' => $folder->label,
            ];
        }

        return ['items' => $items];
    }

    public function actionLibraryList(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $queryText = trim((string)Yii::$app->request->get('q', ''));
        $kind = MediaFile::normalizeKind((string)Yii::$app->request->get('kind', MediaFile::KIND_IMAGE));
        $folderId = (int)Yii::$app->request->get('folder_id', 0);
        $folderSlug = trim((string)Yii::$app->request->get('folder', ''));
        $linkedIds = $this->parseLinkedIds((string)Yii::$app->request->get('linked_ids', ''));

        $query = MediaFile::find()
            ->where(['kind' => $kind])
            ->orderBy(['id' => SORT_DESC])
            ->limit(120);

        if ($folderId > 0) {
            $query->andWhere(['folder_id' => $folderId]);
        } elseif ($folderSlug !== '') {
            $resolvedId = MediaFolder::idBySlug($folderSlug);
            if ($resolvedId !== null) {
                $query->andWhere(['folder_id' => $resolvedId]);
            }
        }

        if ($queryText !== '') {
            $query->andWhere(['like', 'filename', $queryText]);
        }

        $all = $query->all();
        $linkedSet = array_flip($linkedIds);
        $linked = [];
        $rest = [];
        foreach ($all as $media) {
            if (isset($linkedSet[(int)$media->id])) {
                $linked[] = $media;
            } else {
                $rest[] = $media;
            }
        }

        $items = [];
        foreach (array_merge($linked, $rest) as $media) {
            $item = $this->formatMediaItem($media);
            $item['isLinked'] = isset($linkedSet[(int)$media->id]);
            $items[] = $item;
        }

        return ['items' => $items];
    }

    /**
     * @return int[]
     */
    private function parseLinkedIds(string $raw): array
    {
        if ($raw === '') {
            return [];
        }

        $ids = [];
        foreach (explode(',', $raw) as $part) {
            $id = (int)trim($part);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return array<string, mixed>
     */
    private function formatMediaItem(MediaFile $media): array
    {
        $previewVariant = $media->isImage() ? 'mini' : 'original';
        $resolvedPreviewVariant = MediaContentValidator::resolveReadableVariant($media, $previewVariant);
        $previewOk = $resolvedPreviewVariant !== null;
        $urls = $media->isImage()
            ? $media->getPublicUrls()
            : ['original' => $media->getPublicUrl()];

        return [
            'id' => (int)$media->id,
            'url' => $previewOk
                ? $media->getPublicUrl($resolvedPreviewVariant)
                : ($media->isImage() ? $media->getPublicUrl('mini') : $media->getPublicUrl()),
            'urls' => $urls,
            'alt' => $media->alt,
            'filename' => $media->filename,
            'displayFilename' => MediaContentValidator::normalizeFilename((string)$media->filename),
            'previewOk' => $previewOk,
            'kind' => $media->kind,
            'mime' => $media->mime,
            'size' => (int)$media->size,
            'folderId' => $media->folder_id !== null ? (int)$media->folder_id : null,
        ];
    }

    private function findModel(int $id): MediaFile
    {
        $model = MediaFile::findOne($id);
        if ($model === null) {
            throw new NotFoundHttpException('Файл не найден.');
        }

        return $model;
    }

    private function normalizeKindFilter(string $kind): string
    {
        if ($kind === 'all') {
            return '';
        }

        return MediaFile::normalizeKind($kind);
    }

    /**
     * @return array<string, mixed>
     */
    private function listingTileEditorBootstrap(ListingTileService $service): array
    {
        return [
            'config' => $service->getConfig()->toEditorPayload(),
            'canEditFloorGuide' => $this->canEditListingTileFloorGuide(),
            'floorGuideSaveUrl' => Url::to(['/admin/media/listing-tile-floor-guide-save']),
        ];
    }

    private function canEditListingTileFloorGuide(): bool
    {
        /** @var \app\models\AdminUser|null $user */
        $user = Yii::$app->adminUser->identity;

        return $user !== null && $user->canAccess('media');
    }
}
