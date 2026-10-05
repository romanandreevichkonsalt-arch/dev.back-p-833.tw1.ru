<?php

namespace app\modules\admin\controllers;

use app\models\CatalogCollection;
use app\models\CatalogCollectionImage;
use app\models\CatalogDirection;
use app\models\CatalogProduct;
use app\models\MediaFile;
use app\modules\admin\helpers\ReferenceDeleteGuard;
use yii\helpers\ArrayHelper;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class SettingsCollectionController extends SettingsBaseController
{
    public function actionIndex(): string
    {
        $collections = CatalogCollection::find()
            ->with(['image', 'direction', 'collectionImages.media'])
            ->orderBy(['sort_order' => SORT_ASC])
            ->all();

        return $this->render('collection/index', ['collections' => $collections]);
    }

    public function actionCreate(): Response|string
    {
        $model = new CatalogCollection(['is_active' => true, 'sort_order' => 0]);

        if ($this->trySaveModel($model)) {
            $this->syncGallery($model, (array)\Yii::$app->request->post('gallery_media_ids', []));
            \Yii::$app->session->setFlash('success', 'Коллекция создана.');
            return $this->redirect(['index']);
        }

        return $this->render('collection/form', [
            'model' => $model,
            'title' => 'Новая коллекция',
            'directions' => $this->getDirectionOptions(),
            'hasDirections' => CatalogDirection::find()->exists(),
        ]);
    }

    public function actionUpdate(int $id): Response|string
    {
        $model = $this->findModel($id);

        if ($this->trySaveModel($model)) {
            \Yii::$app->session->setFlash('success', 'Коллекция обновлена.');
            return $this->redirect(['index']);
        }

        $model = CatalogCollection::find()
            ->where(['id' => $id])
            ->with(['collectionImages.media', 'direction'])
            ->one() ?? $model;

        return $this->render('collection/form', [
            'model' => $model,
            'title' => 'Редактирование коллекции',
            'directions' => $this->getDirectionOptions(),
            'hasDirections' => CatalogDirection::find()->exists(),
        ]);
    }

    public function actionDelete(int $id): Response
    {
        $model = $this->findModel($id);
        try {
            ReferenceDeleteGuard::ensureCanDelete([
                'есть товары с этой коллекцией' => $model->getProducts(),
            ]);
        } catch (BadRequestHttpException $e) {
            \Yii::$app->session->setFlash('error', $e->getMessage());
            return $this->redirect(['index']);
        }

        $model->delete();
        \Yii::$app->session->setFlash('success', 'Коллекция удалена.');

        return $this->redirect(['index']);
    }

    public function actionGalleryAdd(int $id): array
    {
        \Yii::$app->response->format = Response::FORMAT_JSON;
        $collection = $this->findModel($id);
        $mediaId = (int)\Yii::$app->request->post('mediaId', 0);
        $media = MediaFile::findOne($mediaId);
        if ($media === null) {
            \Yii::$app->response->statusCode = 400;

            return ['message' => 'Файл не найден.'];
        }

        if (CatalogCollectionImage::find()->where(['collection_id' => $collection->id, 'media_file_id' => $mediaId])->exists()) {
            \Yii::$app->response->statusCode = 400;

            return ['message' => 'Это фото уже добавлено.'];
        }

        $sortOrder = (int)CatalogCollectionImage::find()->where(['collection_id' => $collection->id])->count();
        $link = new CatalogCollectionImage([
            'collection_id' => $collection->id,
            'media_file_id' => $mediaId,
            'sort_order' => $sortOrder,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $link->save(false);
        $collection->syncPrimaryImage();

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
        $collection = $this->findModel($id);
        $linkId = (int)\Yii::$app->request->post('linkId', 0);
        $link = CatalogCollectionImage::findOne(['id' => $linkId, 'collection_id' => $collection->id]);
        if ($link === null) {
            \Yii::$app->response->statusCode = 404;

            return ['message' => 'Фото не найдено.'];
        }

        $link->delete();
        $this->reindexGallery($collection);
        $collection->syncPrimaryImage();

        return ['ok' => true];
    }

    public function actionGalleryReorder(int $id): array
    {
        \Yii::$app->response->format = Response::FORMAT_JSON;
        $collection = $this->findModel($id);
        $linkIds = array_values(array_filter(array_map('intval', (array)\Yii::$app->request->post('linkIds', []))));

        if ($linkIds === []) {
            \Yii::$app->response->statusCode = 400;

            return ['message' => 'Не передан порядок фото.'];
        }

        $links = CatalogCollectionImage::find()
            ->where(['collection_id' => $collection->id])
            ->indexBy('id')
            ->all();

        if (count($linkIds) !== count($links)) {
            \Yii::$app->response->statusCode = 400;

            return ['message' => 'Некорректный список фото.'];
        }

        foreach ($linkIds as $linkId) {
            if (!isset($links[$linkId])) {
                \Yii::$app->response->statusCode = 400;

                return ['message' => 'Фото не найдено.'];
            }
        }

        foreach ($linkIds as $index => $linkId) {
            $link = $links[$linkId];
            if ((int)$link->sort_order !== $index) {
                $link->sort_order = $index;
                $link->save(false, ['sort_order']);
            }
        }

        $collection->syncPrimaryImage();

        return ['ok' => true];
    }

    private function syncGallery(CatalogCollection $collection, array $mediaIds): void
    {
        $sortOrder = (int)CatalogCollectionImage::find()->where(['collection_id' => $collection->id])->count();
        foreach ($mediaIds as $mediaId) {
            $mediaId = (int)$mediaId;
            if ($mediaId <= 0 || !MediaFile::find()->where(['id' => $mediaId])->exists()) {
                continue;
            }
            if (CatalogCollectionImage::find()->where(['collection_id' => $collection->id, 'media_file_id' => $mediaId])->exists()) {
                continue;
            }

            $link = new CatalogCollectionImage([
                'collection_id' => $collection->id,
                'media_file_id' => $mediaId,
                'sort_order' => $sortOrder++,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $link->save(false);
        }

        $collection->syncPrimaryImage();
    }

    private function reindexGallery(CatalogCollection $collection): void
    {
        $links = CatalogCollectionImage::find()
            ->where(['collection_id' => $collection->id])
            ->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC])
            ->all();

        foreach ($links as $index => $link) {
            if ((int)$link->sort_order !== $index) {
                $link->sort_order = $index;
                $link->save(false, ['sort_order']);
            }
        }
    }

    private function findModel(int $id): CatalogCollection
    {
        $model = CatalogCollection::findOne($id);
        if ($model === null) {
            throw new NotFoundHttpException('Коллекция не найдена.');
        }

        return $model;
    }

    /**
     * @return array<int|string, string>
     */
    private function getDirectionOptions(): array
    {
        $options = ArrayHelper::map(
            CatalogDirection::find()
                ->where(['is_active' => true])
                ->orderBy(['sort_order' => SORT_ASC])
                ->all(),
            'id',
            'label'
        );

        return ['' => '— выберите направление —'] + $options;
    }
}
