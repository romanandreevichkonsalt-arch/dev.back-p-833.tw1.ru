<?php

namespace app\modules\admin\controllers;

use app\models\CatalogCategory;
use app\models\CatalogBadge;
use app\models\CatalogCollection;
use app\models\CatalogFabricColor;
use app\models\CatalogProduct;
use app\models\CatalogFabricCollection;
use app\models\CatalogModel;
use app\models\CatalogModelDimensionImage;
use app\models\CatalogModelImage;
use app\models\CatalogModelPriceCategoryLink;
use app\models\CatalogPriceCategory;
use app\models\CatalogSubcategory;
use app\models\MediaFile;
use app\services\catalog\CatalogModel3dFileUploadService;
use app\services\catalog\CatalogModelProductSyncService;
use app\exceptions\ApiValidationException;
use app\services\cache\ApiCacheInvalidator;
use app\services\dealer\DealerPromoService;
use yii\helpers\ArrayHelper;
use app\services\import\catalog\CatalogModelImporter;
use app\services\import\catalog\CatalogModelImportOptions;
use app\services\import\catalog\CatalogModelImportResult;
use app\services\import\catalog\CatalogModelImportRunService;
use app\services\search\SearchCatalogPriorityService;
use app\modules\admin\models\CatalogModelSearch;
use Yii;
use yii\helpers\FileHelper;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\UploadedFile;

class CatalogModelController extends BaseController
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
        $searchModel = new CatalogModelSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    public function actionCreate(): Response|string
    {
        $model = new CatalogModel(['is_active' => true, 'sort_order' => 0]);

        if ($this->trySaveWithRelations($model)) {
            $message = 'Модель создана. Товары сгенерированы.';
            $message = $this->appendNoveltyPromoFlash($message);
            \Yii::$app->session->setFlash('success', $message);
            return $this->redirect(['index']);
        }

        return $this->render('form', array_merge($this->getFormViewParams($model), [
            'model' => $model,
            'title' => 'Новая модель',
        ]));
    }

    public function actionFabricGroupBody(int $id, int $fabric_id): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $model = $this->findModel($id);
        $fabricCollectionId = $fabric_id;

        $isLinked = (bool)Yii::$app->db->createCommand(
            'SELECT 1 FROM {{%catalog_model_fabric_collections}} WHERE model_id = :modelId AND fabric_collection_id = :fabricId LIMIT 1',
            ['modelId' => (int)$model->id, 'fabricId' => $fabricCollectionId]
        )->queryScalar();
        if (!$isLinked) {
            Yii::$app->response->statusCode = 403;

            return ['message' => 'Коллекция ткани не привязана к модели.'];
        }

        $fabricCollection = CatalogFabricCollection::find()
            ->where(['id' => $fabricCollectionId, 'is_active' => true])
            ->with(['activeColors.catalogColor', 'activeColors.swatchMedia'])
            ->one();
        if ($fabricCollection === null) {
            Yii::$app->response->statusCode = 404;

            return ['message' => 'Коллекция ткани не найдена.'];
        }

        return [
            'html' => $this->renderPartial('@app/modules/admin/widgets/views/_fabric-group-body', [
                'fabricCollection' => $fabricCollection,
                'productsByColorId' => $this->getProductsByColorIdForFabricCollection($model, $fabricCollection),
                'searchPriority' => (new SearchCatalogPriorityService())->getStateForModel($model),
            ]),
        ];
    }

    public function actionUpdate(int $id): Response|string
    {
        $model = $this->findModel($id);

        if ($this->trySaveWithRelations($model)) {
            $message = 'Модель обновлена. Товары синхронизированы.';
            $message = $this->appendNoveltyPromoFlash($message);
            \Yii::$app->session->setFlash('success', $message);
            return $this->redirect(['index']);
        }

        $model = CatalogModel::find()
            ->where(['id' => $id])
            ->with([
                'modelImages.media',
                'modelInteriorImages.media',
                'modelDimensionImages.media',
                'modelPrices.priceCategory',
                'priceCategoryLinks.priceCategory',
                'fabricCollections',
                'video',
                'file3d',
                'collection',
                'category',
                'subcategory',
            ])
            ->one() ?? $model;

        return $this->render('form', array_merge($this->getFormViewParams($model), [
            'model' => $model,
            'title' => 'Редактирование модели',
        ]));
    }

    public function actionDelete(int $id): Response
    {
        $model = $this->findModel($id);
        $model->delete();
        ApiCacheInvalidator::touch();
        \Yii::$app->session->setFlash('success', 'Модель удалена.');

        return $this->redirect(['index']);
    }

    public function actionGalleryAdd(int $id): array
    {
        \Yii::$app->response->format = Response::FORMAT_JSON;
        $model = $this->findModel($id);
        $mediaId = (int)\Yii::$app->request->post('mediaId', 0);
        $media = MediaFile::findOne($mediaId);
        if ($media === null) {
            \Yii::$app->response->statusCode = 400;

            return ['message' => 'Файл не найден.'];
        }

        $purpose = $this->normalizeImagePurpose((string)\Yii::$app->request->post('purpose', CatalogModelImage::PURPOSE_ANGLE));

        if (CatalogModelImage::find()->where(['model_id' => $model->id, 'media_file_id' => $mediaId])->exists()) {
            \Yii::$app->response->statusCode = 400;

            return ['message' => 'Это фото уже добавлено.'];
        }

        $sortOrder = (int)CatalogModelImage::find()
            ->where(['model_id' => $model->id, 'purpose' => $purpose])
            ->count();
        $link = new CatalogModelImage([
            'model_id' => $model->id,
            'media_file_id' => $mediaId,
            'purpose' => $purpose,
            'sort_order' => $sortOrder,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $link->save(false);

        return [
            'linkId' => (int)$link->id,
            'mediaId' => $mediaId,
            'url' => $media->getPublicUrl(),
            'alt' => $media->alt,
            'filename' => $media->filename,
        ];
    }

    public function actionGalleryDelete(int $id): array
    {
        \Yii::$app->response->format = Response::FORMAT_JSON;
        $model = $this->findModel($id);
        $linkId = (int)\Yii::$app->request->post('linkId', 0);
        $link = CatalogModelImage::findOne(['id' => $linkId, 'model_id' => $model->id]);
        if ($link === null) {
            \Yii::$app->response->statusCode = 404;

            return ['message' => 'Фото не найдено.'];
        }

        $link->delete();
        $this->reindexModelImages($model, $link->purpose);

        return ['ok' => true];
    }

    public function actionGalleryReorder(int $id): array
    {
        \Yii::$app->response->format = Response::FORMAT_JSON;
        $model = $this->findModel($id);
        $purpose = $this->normalizeImagePurpose((string)\Yii::$app->request->post('purpose', CatalogModelImage::PURPOSE_ANGLE));
        $linkIds = array_values(array_filter(array_map('intval', (array)\Yii::$app->request->post('linkIds', []))));

        if ($linkIds === []) {
            \Yii::$app->response->statusCode = 400;

            return ['message' => 'Не передан порядок фото.'];
        }

        $links = CatalogModelImage::find()
            ->where(['model_id' => $model->id, 'purpose' => $purpose])
            ->indexBy('id')
            ->all();

        if (count($linkIds) !== count($links)) {
            \Yii::$app->response->statusCode = 400;

            return ['message' => 'Некорректный список фото.'];
        }

        foreach ($linkIds as $index => $linkId) {
            if (!isset($links[$linkId])) {
                \Yii::$app->response->statusCode = 400;

                return ['message' => 'Фото не найдено.'];
            }
            $link = $links[$linkId];
            if ((int)$link->sort_order !== $index) {
                $link->sort_order = $index;
                $link->save(false, ['sort_order']);
            }
        }

        return ['ok' => true];
    }

    public function actionDimensionGalleryAdd(int $id): array
    {
        \Yii::$app->response->format = Response::FORMAT_JSON;
        $model = $this->findModel($id);
        $mediaId = (int)\Yii::$app->request->post('mediaId', 0);
        $media = MediaFile::findOne($mediaId);
        if ($media === null) {
            \Yii::$app->response->statusCode = 400;

            return ['message' => 'Файл не найден.'];
        }

        if (CatalogModelDimensionImage::find()->where(['model_id' => $model->id, 'media_file_id' => $mediaId])->exists()) {
            \Yii::$app->response->statusCode = 400;

            return ['message' => 'Это фото уже добавлено.'];
        }

        $sortOrder = (int)CatalogModelDimensionImage::find()->where(['model_id' => $model->id])->count();
        $link = new CatalogModelDimensionImage([
            'model_id' => $model->id,
            'media_file_id' => $mediaId,
            'sort_order' => $sortOrder,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $link->save(false);

        return [
            'linkId' => (int)$link->id,
            'mediaId' => $mediaId,
            'url' => $media->getPublicUrl(),
            'alt' => $media->alt,
            'filename' => $media->filename,
        ];
    }

    public function actionDimensionGalleryDelete(int $id): array
    {
        \Yii::$app->response->format = Response::FORMAT_JSON;
        $model = $this->findModel($id);
        $linkId = (int)\Yii::$app->request->post('linkId', 0);
        $link = CatalogModelDimensionImage::findOne(['id' => $linkId, 'model_id' => $model->id]);
        if ($link === null) {
            \Yii::$app->response->statusCode = 404;

            return ['message' => 'Фото не найдено.'];
        }

        $link->delete();
        $this->reindexDimensionGallery($model);

        return ['ok' => true];
    }

    public function actionDimensionGalleryReorder(int $id): array
    {
        \Yii::$app->response->format = Response::FORMAT_JSON;
        $model = $this->findModel($id);
        $linkIds = array_values(array_filter(array_map('intval', (array)\Yii::$app->request->post('linkIds', []))));

        if ($linkIds === []) {
            \Yii::$app->response->statusCode = 400;

            return ['message' => 'Не передан порядок фото.'];
        }

        $links = CatalogModelDimensionImage::find()
            ->where(['model_id' => $model->id])
            ->indexBy('id')
            ->all();

        if (count($linkIds) !== count($links)) {
            \Yii::$app->response->statusCode = 400;

            return ['message' => 'Некорректный список фото.'];
        }

        foreach ($linkIds as $index => $linkId) {
            if (!isset($links[$linkId])) {
                \Yii::$app->response->statusCode = 400;

                return ['message' => 'Фото не найдено.'];
            }
            $link = $links[$linkId];
            if ((int)$link->sort_order !== $index) {
                $link->sort_order = $index;
                $link->save(false, ['sort_order']);
            }
        }

        return ['ok' => true];
    }

    private function importModelFile3dFromUrlIfNeeded(CatalogModel $model): bool
    {
        $url = trim((string)$model->file_3d_url);
        if ($url === '' || !preg_match('~^https?://~i', $url)) {
            return true;
        }

        if ((int)$model->file_3d_id > 0) {
            return true;
        }

        try {
            (new CatalogModel3dFileUploadService())->uploadFromUrl($model, $url);
        } catch (ApiValidationException $exception) {
            \Yii::$app->session->setFlash('error', $exception->getMessage());

            return false;
        }

        return true;
    }

    private function trySaveWithRelations(CatalogModel $model): bool
    {
        if (!$model->load(\Yii::$app->request->post())) {
            return false;
        }

        $postedPriceRows = (array)\Yii::$app->request->post('model_prices', []);
        $normalizedPrices = CatalogModel::normalizePostedPrices($postedPriceRows);
        $priceError = $model->validatePricesComplete($postedPriceRows);
        if ($priceError !== null) {
            \Yii::$app->session->setFlash('error', $priceError);

            return false;
        }

        $wasNew = $model->isNewRecord;
        $oldBadgeId = $model->isNewRecord ? null : $model->getOldAttribute('badge_id');

        if (!$model->save()) {
            \Yii::$app->session->setFlash('error', 'Не удалось сохранить. Исправьте ошибки в форме.');

            return false;
        }

        if (!$this->importModelFile3dFromUrlIfNeeded($model)) {
            return false;
        }

        $model->syncPriceCategoryLinks(array_keys($normalizedPrices));
        $model->syncPrices($normalizedPrices);
        $model->syncFabricCollectionLinks((array)\Yii::$app->request->post('fabric_collection_ids', []));

        if ($wasNew) {
            $this->syncPendingGalleriesFromPost($model);
        }

        \Yii::$container->get(CatalogModelProductSyncService::class)->syncForModel($model);
        $this->syncProductVariantsFromPost($model, (array)\Yii::$app->request->post('product_variants', []));
        $priorityError = (new \app\services\search\SearchCatalogPriorityService())->saveFromModelForm(
            $model,
            \Yii::$app->request->post()
        );
        if ($priorityError !== null) {
            \Yii::$app->session->setFlash('error', $priorityError);

            return false;
        }
        $this->maybeGrantNoveltyPromos($model, $wasNew, $oldBadgeId);
        ApiCacheInvalidator::touch();

        return true;
    }

    private const NOVELTY_PROMO_GRANT_ALL = 'grant_all';
    private const NOVELTY_PROMO_CREATE_ONLY = 'create_only';

    private function syncPendingGalleriesFromPost(CatalogModel $model): void
    {
        $galleryMedia = \Yii::$app->request->post('gallery_media_ids', []);
        if (!is_array($galleryMedia)) {
            $galleryMedia = [];
        }

        if (array_key_exists('angle', $galleryMedia) || array_key_exists('interior', $galleryMedia)) {
            $this->syncPendingModelGallery($model, CatalogModelImage::PURPOSE_ANGLE, (array)($galleryMedia['angle'] ?? []));
            $this->syncPendingModelGallery($model, CatalogModelImage::PURPOSE_INTERIOR, (array)($galleryMedia['interior'] ?? []));
        } else {
            $this->syncPendingModelGallery($model, CatalogModelImage::PURPOSE_ANGLE, $galleryMedia);
        }

        $this->syncPendingDimensionGallery($model, (array)\Yii::$app->request->post('dimension_media_ids', []));
    }

    /**
     * @param int[]|string[] $mediaIds
     */
    private function syncPendingModelGallery(CatalogModel $model, string $purpose, array $mediaIds): void
    {
        $sortOrder = (int)CatalogModelImage::find()
            ->where(['model_id' => $model->id, 'purpose' => $purpose])
            ->count();

        foreach ($mediaIds as $mediaId) {
            $mediaId = (int)$mediaId;
            if ($mediaId <= 0 || MediaFile::find()->where(['id' => $mediaId])->exists() === false) {
                continue;
            }
            if (CatalogModelImage::find()->where([
                'model_id' => $model->id,
                'media_file_id' => $mediaId,
                'purpose' => $purpose,
            ])->exists()) {
                continue;
            }

            $link = new CatalogModelImage([
                'model_id' => $model->id,
                'media_file_id' => $mediaId,
                'purpose' => $purpose,
                'sort_order' => $sortOrder++,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $link->save(false);
        }
    }

    /**
     * @param int[]|string[] $mediaIds
     */
    private function syncPendingDimensionGallery(CatalogModel $model, array $mediaIds): void
    {
        $sortOrder = (int)CatalogModelDimensionImage::find()->where(['model_id' => $model->id])->count();

        foreach ($mediaIds as $mediaId) {
            $mediaId = (int)$mediaId;
            if ($mediaId <= 0 || MediaFile::find()->where(['id' => $mediaId])->exists() === false) {
                continue;
            }
            if (CatalogModelDimensionImage::find()->where([
                'model_id' => $model->id,
                'media_file_id' => $mediaId,
            ])->exists()) {
                continue;
            }

            $link = new CatalogModelDimensionImage([
                'model_id' => $model->id,
                'media_file_id' => $mediaId,
                'sort_order' => $sortOrder++,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $link->save(false);
        }
    }

    /**
     * @param array<int|string, array<string, mixed>> $variants
     */
    private function syncProductVariantsFromPost(CatalogModel $model, array $variants): void
    {
        foreach ($variants as $colorId => $row) {
            if (!is_array($row)) {
                continue;
            }
            $colorId = (int)$colorId;
            if ($colorId <= 0) {
                continue;
            }

            $product = CatalogProduct::findOne([
                'model_id' => $model->id,
                'fabric_color_id' => $colorId,
            ]);
            if ($product === null) {
                continue;
            }

            if (array_key_exists('image_id', $row)) {
                $imageId = (int)($row['image_id'] ?? 0);
                $product->image_id = $imageId > 0 ? $imageId : null;
            }
            if (array_key_exists('quantity', $row)) {
                $product->quantity = max(0, (int)($row['quantity'] ?? 0));
            }
            if (array_key_exists('is_active', $row)) {
                $product->is_active = (bool)$row['is_active'];
            }

            $product->save(false);
        }
    }

    private function normalizeImagePurpose(string $purpose): string
    {
        return $purpose === CatalogModelImage::PURPOSE_INTERIOR
            ? CatalogModelImage::PURPOSE_INTERIOR
            : CatalogModelImage::PURPOSE_ANGLE;
    }

    private function reindexModelImages(CatalogModel $model, string $purpose): void
    {
        $links = CatalogModelImage::find()
            ->where(['model_id' => $model->id, 'purpose' => $purpose])
            ->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC])
            ->all();

        foreach ($links as $index => $link) {
            if ((int)$link->sort_order !== $index) {
                $link->sort_order = $index;
                $link->save(false, ['sort_order']);
            }
        }
    }

    private function reindexGallery(CatalogModel $model): void
    {
        $this->reindexModelImages($model, CatalogModelImage::PURPOSE_ANGLE);
        $this->reindexModelImages($model, CatalogModelImage::PURPOSE_INTERIOR);
    }

    private function reindexDimensionGallery(CatalogModel $model): void
    {
        $links = CatalogModelDimensionImage::find()
            ->where(['model_id' => $model->id])
            ->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC])
            ->all();

        foreach ($links as $index => $link) {
            if ((int)$link->sort_order !== $index) {
                $link->sort_order = $index;
                $link->save(false, ['sort_order']);
            }
        }
    }

    public function actionCascadeOptions(): array
    {
        \Yii::$app->response->format = Response::FORMAT_JSON;

        $categoryId = (int)\Yii::$app->request->get('category_id', 0);

        if ($categoryId > 0) {
            $subcategories = CatalogSubcategory::find()
                ->where(['category_id' => $categoryId, 'is_active' => true])
                ->orderBy(['sort_order' => SORT_ASC, 'label' => SORT_ASC])
                ->all();

            return [
                'subcategories' => array_map(static fn (CatalogSubcategory $s): array => [
                    'id' => (int)$s->id,
                    'label' => $s->label,
                    'slug' => $s->slug,
                ], $subcategories),
            ];
        }

        \Yii::$app->response->statusCode = 400;

        return ['message' => 'Укажите category_id.'];
    }

    public function actionPriceCategoryCreate(): array
    {
        \Yii::$app->response->format = Response::FORMAT_JSON;

        $modelId = (int)\Yii::$app->request->post('model_id', 0);
        $afterNumber = (int)\Yii::$app->request->post('after_number', 0);

        if ($afterNumber <= 0 && $modelId > 0) {
            $afterNumber = (int)CatalogModelPriceCategoryLink::find()
                ->alias('link')
                ->innerJoin(
                    '{{%catalog_price_categories}} category',
                    'category.id = link.price_category_id'
                )
                ->where(['link.model_id' => $modelId])
                ->max('category.number');
        }

        $nextNumber = max(1, $afterNumber + 1);

        $excludeIds = array_values(array_filter(array_map('intval', (array)\Yii::$app->request->post('exclude_category_ids', []))));
        $numberQuery = CatalogPriceCategory::find()
            ->where(['is_active' => true])
            ->andWhere(['>=', 'number', $nextNumber]);

        if ($excludeIds !== []) {
            $numberQuery->andWhere(['not in', 'id', $excludeIds]);
        }

        $category = $numberQuery
            ->orderBy(['number' => SORT_ASC, 'id' => SORT_ASC])
            ->one();

        if ($category === null) {
            while (CatalogPriceCategory::find()->where(['number' => $nextNumber])->exists()) {
                $nextNumber++;
            }

            $category = new CatalogPriceCategory([
                'number' => $nextNumber,
                'is_active' => true,
            ]);

            if (!$category->save()) {
                \Yii::$app->response->statusCode = 422;
                $errors = $category->getFirstErrors();

                return ['message' => reset($errors) ?: 'Не удалось создать категорию.'];
            }
        }

        return [
            'id' => (int)$category->id,
            'label' => $category->getDisplayLabel(),
            'number' => (int)$category->number,
        ];
    }

    private function findModel(int $id): CatalogModel
    {
        $model = CatalogModel::findOne($id);
        if ($model === null) {
            throw new NotFoundHttpException('Модель не найдена.');
        }

        return $model;
    }

    /**
     * @return array<string, mixed>
     */
    private function getFormViewParams(CatalogModel $model): array
    {
        $this->syncModelCategoryFromSubcategory($model);

        $linkedFabricIds = [];
        if (!$model->isNewRecord) {
            $linkedFabricIds = \Yii::$app->db->createCommand(
                'SELECT fabric_collection_id FROM {{%catalog_model_fabric_collections}} WHERE model_id = :id',
                ['id' => $model->id]
            )->queryColumn();
        }

        $priceMap = [];
        $priceCategories = CatalogPriceCategory::findActiveOrdered();
        $visiblePriceCategories = $this->resolveVisiblePriceCategories($model, $priceCategories, $priceMap);

        return [
            'collections' => $this->getCollectionOptions(),
            'collectionOptionAttributes' => $this->getCollectionOptionAttributes(),
            'categories' => $this->getCategoryOptions(),
            'categoryOptionAttributes' => $this->getCategoryOptionAttributes(),
            'subcategories' => $this->getSubcategoriesForCategory(
                (int)$model->category_id,
                (int)$model->subcategory_id
            ),
            'subcategoryOptionAttributes' => $this->getSubcategoryOptionAttributes(
                (int)$model->category_id,
                (int)$model->subcategory_id
            ),
            'priceCategories' => $priceCategories,
            'visiblePriceCategories' => $visiblePriceCategories,
            'priceMap' => $priceMap,
            'badges' => ['' => '—'] + ArrayHelper::map(
                CatalogBadge::find()->where(['is_active' => true])->orderBy(['sort_order' => SORT_ASC])->all(),
                'id',
                'label'
            ),
            'fabricCollections' => $this->findFabricCollectionsForModelForm($model),
            'linkedFabricIds' => array_map('intval', $linkedFabricIds),
            'productsByColorId' => [],
            'noveltyBadgeIds' => $this->getNoveltyBadgeIds(),
            'hasProducts' => $this->modelHasProductsContext($model, $linkedFabricIds),
            'noveltyPromoCodePreview' => $this->resolveNoveltyPromoCodePreview($model),
        ];
    }

    private function resolveNoveltyPromoCodePreview(CatalogModel $model): string
    {
        if (!$model->collection_id) {
            return 'NOVINKA_{коллекция}';
        }

        $collection = $model->collection ?? CatalogCollection::findOne((int)$model->collection_id);
        if ($collection === null) {
            return 'NOVINKA_{коллекция}';
        }

        $code = DealerPromoService::buildNoveltyPromoCode((string)$collection->slug);

        return $code !== '' ? $code : 'NOVINKA_{коллекция}';
    }

    /**
     * @return list<int>
     */
    private function getNoveltyBadgeIds(): array
    {
        return CatalogBadge::find()
            ->select(['id'])
            ->where(['is_active' => true, 'variant' => CatalogBadge::VARIANT_NEW])
            ->column();
    }

    /**
     * @param int[] $linkedFabricIds
     */
    private function modelHasProductsContext(CatalogModel $model, array $linkedFabricIds): bool
    {
        if (!$model->isNewRecord) {
            $hasProducts = CatalogProduct::find()
                ->where(['model_id' => (int)$model->id])
                ->exists();
            if ($hasProducts) {
                return true;
            }
        }

        return $linkedFabricIds !== [];
    }

    /**
     * @param CatalogPriceCategory[] $priceCategories
     * @param array<int, string> $priceMap
     * @return CatalogPriceCategory[]
     */
    private function resolveVisiblePriceCategories(
        CatalogModel $model,
        array $priceCategories,
        array &$priceMap
    ): array {
        $byId = ArrayHelper::index($priceCategories, 'id');

        if (\Yii::$app->request->isPost) {
            $ids = [];
            foreach ((array)\Yii::$app->request->post('model_prices', []) as $key => $value) {
                if (is_array($value)) {
                    $categoryId = (int)($value['category_id'] ?? 0);
                    $display = (string)($value['price_display'] ?? '');
                } else {
                    $categoryId = (int)$key;
                    $display = (string)$value;
                }

                if ($categoryId <= 0) {
                    continue;
                }

                $ids[] = $categoryId;
                $priceMap[$categoryId] = $display;
            }

            return $this->mapPriceCategoriesByIds($ids, $byId);
        }

        $ids = [];
        if (!$model->isNewRecord) {
            $links = CatalogModelPriceCategoryLink::find()
                ->where(['model_id' => $model->id])
                ->with('priceCategory')
                ->orderBy(['sort_order' => SORT_ASC, 'price_category_id' => SORT_ASC])
                ->all();

            foreach ($links as $link) {
                $ids[] = (int)$link->price_category_id;
            }

            foreach ($model->modelPrices as $price) {
                $priceMap[(int)$price->price_category_id] = $price->price_display;
            }
        }

        if ($ids === []) {
            $ids = CatalogModel::getDefaultPriceCategoryIds();
        }

        return $this->mapPriceCategoriesByIds($ids, $byId);
    }

    /**
     * @param int[] $ids
     * @param array<int, CatalogPriceCategory> $byId
     * @return CatalogPriceCategory[]
     */
    private function mapPriceCategoriesByIds(array $ids, array $byId): array
    {
        $categories = [];
        foreach ($ids as $id) {
            if (isset($byId[$id])) {
                $categories[] = $byId[$id];
            }
        }

        usort($categories, static function (CatalogPriceCategory $a, CatalogPriceCategory $b): int {
            return [$a->sort_order, $a->number, $a->id] <=> [$b->sort_order, $b->number, $b->id];
        });

        return $categories;
    }

    /**
     * @return CatalogFabricCollection[]
     */
    private function findFabricCollectionsForModelForm(CatalogModel $model): array
    {
        $query = CatalogFabricCollection::find()
            ->where(['is_active' => true])
            ->orderBy(['sort_order' => SORT_ASC, 'name' => SORT_ASC]);

        if ($model->isNewRecord) {
            $query->with(['activeColors.catalogColor', 'activeColors.swatchMedia']);
        }

        $collections = $query->all();
        if (!$model->isNewRecord) {
            $this->attachActiveColorsCount($collections);
        }

        return $collections;
    }

    /**
     * @param CatalogFabricCollection[] $collections
     */
    private function attachActiveColorsCount(array $collections): void
    {
        if ($collections === []) {
            return;
        }

        $collectionIds = array_map(
            static fn (CatalogFabricCollection $collection): int => (int)$collection->id,
            $collections,
        );

        $rows = CatalogFabricColor::find()
            ->select(['fabric_collection_id', 'cnt' => 'COUNT(*)'])
            ->where(['fabric_collection_id' => $collectionIds, 'is_active' => true])
            ->groupBy('fabric_collection_id')
            ->asArray()
            ->all();

        $countsByCollectionId = [];
        foreach ($rows as $row) {
            $countsByCollectionId[(int)$row['fabric_collection_id']] = (int)$row['cnt'];
        }

        foreach ($collections as $collection) {
            $collection->activeColorsCount = $countsByCollectionId[(int)$collection->id] ?? 0;
        }
    }

    /**
     * @return array<int, CatalogProduct>
     */
    private function getProductsByColorIdForFabricCollection(
        CatalogModel $model,
        CatalogFabricCollection $fabricCollection,
    ): array {
        if ($model->isNewRecord) {
            return [];
        }

        $colorIds = [];
        foreach ($fabricCollection->activeColors as $color) {
            $colorIds[] = (int)$color->id;
        }
        if ($colorIds === []) {
            return [];
        }

        $map = [];
        foreach (CatalogProduct::find()
            ->where(['model_id' => (int)$model->id, 'fabric_color_id' => $colorIds])
            ->with(['fabricColor.swatchMedia', 'image'])
            ->all() as $product) {
            $fabricColorId = (int)$product->fabric_color_id;
            if ($fabricColorId > 0) {
                $map[$fabricColorId] = $product;
            }
        }

        return $map;
    }

    /**
     * @return array<int|string, string>
     */
    private function getCollectionOptions(): array
    {
        $collections = CatalogCollection::find()
            ->where(['is_active' => true])
            ->with('direction')
            ->orderBy(['sort_order' => SORT_ASC, 'name' => SORT_ASC])
            ->all();

        $options = ['' => '—'];
        foreach ($collections as $collection) {
            $label = $collection->getDisplayName();
            if ($collection->direction !== null) {
                $label .= ' (' . $collection->direction->label . ')';
            }
            $options[(int)$collection->id] = $label;
        }

        return $options;
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function getCollectionOptionAttributes(): array
    {
        $attributes = [];
        foreach (CatalogCollection::find()->where(['is_active' => true])->all() as $collection) {
            $attributes[(int)$collection->id] = [
                'data-slug' => $collection->slug,
                'data-name' => $collection->getDisplayName(),
            ];
        }

        return $attributes;
    }

    private function getCategoryOptionAttributes(): array
    {
        $attributes = [];
        foreach (CatalogCategory::find()->where(['is_active' => true])->all() as $category) {
            $attributes[(int)$category->id] = [
                'data-slug' => $category->slug,
            ];
        }

        return $attributes;
    }

    /**
     * @return array<int|string, string>
     */
    private function getCategoryOptions(): array
    {
        return ['' => '—'] + ArrayHelper::map(
            CatalogCategory::find()
                ->where(['is_active' => true])
                ->orderBy(['sort_order' => SORT_ASC, 'label' => SORT_ASC])
                ->all(),
            'id',
            'label'
        );
    }

    private function syncModelCategoryFromSubcategory(CatalogModel $model): void
    {
        if ($model->isNewRecord || !$model->subcategory_id) {
            return;
        }

        $subcategory = $model->subcategory ?? CatalogSubcategory::findOne((int)$model->subcategory_id);
        if ($subcategory === null) {
            return;
        }

        if ((int)$model->category_id !== (int)$subcategory->category_id) {
            $model->category_id = (int)$subcategory->category_id;
        }
    }

    /**
     * @return array<int|string, string>
     */
    private function getSubcategoriesForCategory(int $categoryId, int $ensureSubcategoryId = 0): array
    {
        if ($categoryId <= 0) {
            return $this->appendEnsuredSubcategory(['' => '—'], $ensureSubcategoryId);
        }

        $options = ['' => '—'] + ArrayHelper::map(
            CatalogSubcategory::find()
                ->where(['category_id' => $categoryId, 'is_active' => true])
                ->orderBy(['sort_order' => SORT_ASC, 'label' => SORT_ASC])
                ->all(),
            'id',
            'label'
        );

        return $this->appendEnsuredSubcategory($options, $ensureSubcategoryId);
    }

    /**
     * @param array<int|string, string> $options
     * @return array<int|string, string>
     */
    private function appendEnsuredSubcategory(array $options, int $ensureSubcategoryId): array
    {
        if ($ensureSubcategoryId <= 0 || isset($options[$ensureSubcategoryId])) {
            return $options;
        }

        $subcategory = CatalogSubcategory::findOne($ensureSubcategoryId);
        if ($subcategory === null) {
            return $options;
        }

        $options[(int)$subcategory->id] = $subcategory->label;

        return $options;
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function getSubcategoryOptionAttributes(int $categoryId, int $ensureSubcategoryId = 0): array
    {
        if ($categoryId <= 0 && $ensureSubcategoryId <= 0) {
            return [];
        }

        $attributes = [];
        if ($categoryId > 0) {
            foreach (CatalogSubcategory::find()
                ->where(['category_id' => $categoryId, 'is_active' => true])
                ->orderBy(['sort_order' => SORT_ASC, 'label' => SORT_ASC])
                ->all() as $subcategory) {
                $attributes[(int)$subcategory->id] = $this->buildSubcategoryOptionAttributes($subcategory);
            }
        }

        if ($ensureSubcategoryId > 0 && !isset($attributes[$ensureSubcategoryId])) {
            $subcategory = CatalogSubcategory::findOne($ensureSubcategoryId);
            if ($subcategory !== null) {
                $attributes[(int)$subcategory->id] = $this->buildSubcategoryOptionAttributes($subcategory);
            }
        }

        return $attributes;
    }

    /**
     * @return array<string, string>
     */
    private function buildSubcategoryOptionAttributes(CatalogSubcategory $subcategory): array
    {
        return [
            'data-slug' => $subcategory->slug,
            'data-label' => $subcategory->label,
            'data-name' => $subcategory->label,
        ];
    }

    public function actionImportStart(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $uploadedFile = UploadedFile::getInstanceByName('price_list_file');
        if ($uploadedFile === null) {
            Yii::$app->response->statusCode = 400;

            return ['message' => 'Выберите файл прайс-листа (.xlsx).'];
        }

        $options = new CatalogModelImportOptions();
        $this->applyPostedModelImportOptions($options);
        $options->skipIfNoFabric = (bool)Yii::$app->request->post('skip_if_no_fabric', false);
        $options->userId = Yii::$app->user->id ?? null;
        $options->filename = (string)$uploadedFile->name;

        try {
            $service = new CatalogModelImportRunService();
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

        $service = new CatalogModelImportRunService();

        return $service->getStatusPayload($id);
    }

    /** @deprecated Используйте import-start + polling */
    public function actionImportRun(): Response|string
    {
        $uploadedFile = \yii\web\UploadedFile::getInstanceByName('price_list_file');
        if ($uploadedFile === null) {
            Yii::$app->session->setFlash('error', 'Выберите файл прайс-листа (.xlsx).');

            return $this->redirect(['index']);
        }

        $extension = strtolower((string)$uploadedFile->extension);
        if (!in_array($extension, ['xlsx', 'xls'], true)) {
            Yii::$app->session->setFlash('error', 'Допустимы только файлы Excel (.xlsx, .xls).');

            return $this->redirect(['index']);
        }

        $importDir = Yii::getAlias('@runtime/catalog-model-import');
        FileHelper::createDirectory($importDir);
        $storedPath = $importDir . '/' . uniqid('price_list_', true) . '.' . $extension;
        if (!$uploadedFile->saveAs($storedPath)) {
            Yii::$app->session->setFlash('error', 'Не удалось сохранить загруженный файл.');

            return $this->redirect(['index']);
        }

        $options = new CatalogModelImportOptions();
        $options->dryRun = (bool)Yii::$app->request->post('dry_run', false);
        $options->updateExisting = (bool)Yii::$app->request->post('update_existing', false);
        $options->conflictResolution = $this->resolvePostedImportConflictAction($options->updateExisting);
        $options->filename = $uploadedFile->name;

        try {
            $importer = new CatalogModelImporter();
            $result = $importer->import($storedPath, $options);
        } catch (\Throwable $e) {
            @unlink($storedPath);
            Yii::$app->session->setFlash('error', 'Ошибка импорта: ' . $e->getMessage());

            return $this->redirect(['index']);
        }

        if ($result->aborted && $result->pendingConflict !== null) {
            Yii::$app->session->set('catalog_model_import_state', [
                'stored_path' => $storedPath,
                'filename' => $uploadedFile->name,
                'dry_run' => $options->dryRun,
                'result' => $result->toArray(),
            ]);

            return $this->render('import-conflict', [
                'conflict' => $result->pendingConflict,
                'result' => $result,
            ]);
        }

        @unlink($storedPath);

        $flashKey = $result->success && ($result->stats['errors'] ?? 0) === 0 ? 'success' : 'warning';
        Yii::$app->session->setFlash($flashKey, $this->buildModelImportFlashMessage($result));
        if (!$options->dryRun) {
            ApiCacheInvalidator::touch();
        }

        return $this->redirect(['index']);
    }

    public function actionImportResolve(): array|Response|string
    {
        $runId = (int)Yii::$app->request->post('run_id', 0);
        if ($runId > 0) {
            Yii::$app->response->format = Response::FORMAT_JSON;

            $action = (string)Yii::$app->request->post('conflict_action', '');
            if ($action === '') {
                Yii::$app->response->statusCode = 400;

                return ['message' => 'Не указано действие для конфликта.'];
            }

            try {
                $service = new CatalogModelImportRunService();
                $run = $service->resolveConflict($runId, $action);

                return $service->getStatusPayload((int)$run->id);
            } catch (\Throwable $e) {
                Yii::$app->response->statusCode = 400;

                return ['message' => $e->getMessage()];
            }
        }

        $state = Yii::$app->session->get('catalog_model_import_state');
        if (!is_array($state) || empty($state['stored_path']) || !is_file($state['stored_path'])) {
            Yii::$app->session->setFlash('error', 'Сессия импорта истекла. Загрузите файл снова.');

            return $this->redirect(['index']);
        }

        $action = (string)Yii::$app->request->post('conflict_action', '');
        $resolution = match ($action) {
            'update' => CatalogModelImportOptions::CONFLICT_UPDATE,
            'skip' => CatalogModelImportOptions::CONFLICT_SKIP,
            default => CatalogModelImportOptions::CONFLICT_ABORT,
        };

        $options = new CatalogModelImportOptions();
        $options->dryRun = (bool)($state['dry_run'] ?? false);
        $options->updateExisting = $resolution === CatalogModelImportOptions::CONFLICT_UPDATE;
        $options->conflictResolution = $resolution;
        $options->filename = (string)($state['filename'] ?? '');

        try {
            $importer = new CatalogModelImporter();
            $result = $importer->import((string)$state['stored_path'], $options);
        } catch (\Throwable $e) {
            @unlink((string)$state['stored_path']);
            Yii::$app->session->remove('catalog_model_import_state');
            Yii::$app->session->setFlash('error', 'Ошибка импорта: ' . $e->getMessage());

            return $this->redirect(['index']);
        }

        if ($result->aborted && $result->pendingConflict !== null) {
            Yii::$app->session->set('catalog_model_import_state', [
                'stored_path' => $state['stored_path'],
                'filename' => $options->filename,
                'dry_run' => $options->dryRun,
                'result' => $result->toArray(),
            ]);

            return $this->render('import-conflict', [
                'conflict' => $result->pendingConflict,
                'result' => $result,
            ]);
        }

        @unlink((string)$state['stored_path']);
        Yii::$app->session->remove('catalog_model_import_state');

        $flashKey = $result->success && ($result->stats['errors'] ?? 0) === 0 ? 'success' : 'warning';
        Yii::$app->session->setFlash($flashKey, $this->buildModelImportFlashMessage($result));
        if (!$options->dryRun) {
            ApiCacheInvalidator::touch();
        }

        return $this->redirect(['index']);
    }

    private function applyPostedModelImportOptions(CatalogModelImportOptions $options): void
    {
        $posted = (string)Yii::$app->request->post(
            'conflict_resolution',
            \app\modules\admin\helpers\RegistryImportPostedOptions::MODE_SKIP
        );
        $parsed = \app\modules\admin\helpers\RegistryImportPostedOptions::parseConflictResolution(
            $posted,
            CatalogModelImportOptions::CONFLICT_SKIP,
            CatalogModelImportOptions::CONFLICT_UPDATE
        );
        $options->conflictResolution = $parsed['conflictResolution'];
        $options->updateExisting = $parsed['conflictResolution'] === CatalogModelImportOptions::CONFLICT_UPDATE;
        $options->importMedia = $parsed['importMedia'];
    }

    private function resolvePostedImportConflictAction(bool $updateExisting): string
    {
        if ($updateExisting) {
            return CatalogModelImportOptions::CONFLICT_UPDATE;
        }

        $action = (string)Yii::$app->request->post('conflict_action', '');
        if ($action === 'update') {
            return CatalogModelImportOptions::CONFLICT_UPDATE;
        }
        if ($action === 'skip') {
            return CatalogModelImportOptions::CONFLICT_SKIP;
        }
        if ($action === 'abort') {
            return CatalogModelImportOptions::CONFLICT_ABORT;
        }

        return CatalogModelImportOptions::CONFLICT_SKIP;
    }

    private function buildModelImportFlashMessage(CatalogModelImportResult $result): string
    {
        $stats = $result->stats;
        $parts = [
            'создано: ' . (int)($stats['models_created'] ?? 0),
            'обновлено: ' . (int)($stats['models_updated'] ?? 0),
            'пропущено: ' . (int)($stats['models_skipped'] ?? 0),
        ];
        if (($stats['models_conflict'] ?? 0) > 0) {
            $parts[] = 'конфликтов: ' . (int)$stats['models_conflict'];
        }
        if (($stats['errors'] ?? 0) > 0) {
            $parts[] = 'ошибок: ' . (int)$stats['errors'];
        }

        return 'Импорт моделей завершён (' . implode(', ', $parts) . ').';
    }

    private function maybeGrantNoveltyPromos(CatalogModel $model, bool $wasNew, mixed $oldBadgeId): void
    {
        if (!$this->isNoveltyBadgeId($model->badge_id)) {
            return;
        }

        if (!$wasNew && (int)$oldBadgeId === (int)$model->badge_id) {
            return;
        }

        if (!$this->modelHasProductsContext($model, (array)\Yii::$app->request->post('fabric_collection_ids', []))) {
            return;
        }

        $action = (string)\Yii::$app->request->post('novelty_promo_action', self::NOVELTY_PROMO_CREATE_ONLY);
        $promoService = new DealerPromoService();
        $template = \app\models\PromoCodeTemplate::findExhibitionTemplate();
        $discount = $template !== null ? (float)$template->discount_percent : 10.0;
        $promoTemplate = $promoService->ensureNoveltyPromoTemplate((int)$model->id, $discount);
        if ($promoTemplate === null) {
            \Yii::$app->session->set('noveltyPromoFailed', true);

            return;
        }

        $promoCode = $promoTemplate->code;
        if ($action !== self::NOVELTY_PROMO_GRANT_ALL) {
            \Yii::$app->session->set('noveltyPromoCode', $promoCode);
            \Yii::$app->session->set('noveltyPromoCreateOnly', true);

            return;
        }

        $count = $promoService->grantNoveltyPromosForModel((int)$model->id, $discount);
        \Yii::$app->session->set('noveltyPromoCode', $promoCode);
        if ($count > 0) {
            \Yii::$app->session->set('noveltyPromoGrantCount', $count);
        } else {
            \Yii::$app->session->set('noveltyPromoGrantSkipped', true);
        }
    }

    private function isNoveltyBadgeId(mixed $badgeId): bool
    {
        if ($badgeId === null || (int)$badgeId <= 0) {
            return false;
        }

        $badge = CatalogBadge::findOne((int)$badgeId);

        return $badge !== null && $badge->variant === CatalogBadge::VARIANT_NEW;
    }

    private function appendNoveltyPromoFlash(string $message): string
    {
        if (\Yii::$app->session->remove('noveltyPromoFailed')) {
            $message .= ' Не удалось создать промокод новинки: проверьте, что у модели выбрана коллекция.';
        }

        $count = (int)\Yii::$app->session->remove('noveltyPromoGrantCount');
        $promoCode = (string)\Yii::$app->session->remove('noveltyPromoCode');
        $createOnly = (bool)\Yii::$app->session->remove('noveltyPromoCreateOnly');
        $grantSkipped = (bool)\Yii::$app->session->remove('noveltyPromoGrantSkipped');

        if ($promoCode === '') {
            return $message;
        }

        if ($count > 0) {
            $message .= " Промокод {$promoCode} создан и выдан {$count} дилерам.";
        } elseif ($createOnly) {
            $message .= " Промокод {$promoCode} создан. Чтобы дилеры увидели его в «Мои бонусы», выдайте промокод из /admin/promo-code или карточки дилера.";
        } elseif ($grantSkipped) {
            $message .= " Промокод {$promoCode} создан. Выдача не потребовалась — у всех дилеров уже есть активный «{$promoCode}». Можно выдать вручную из карточки промокода или дилера.";
        } else {
            $message .= " Промокод {$promoCode} создан.";
        }

        return $message;
    }
}
