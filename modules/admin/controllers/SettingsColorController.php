<?php

namespace app\modules\admin\controllers;

use app\models\CatalogColor;
use app\models\CatalogColorImage;
use app\models\CatalogFabricColor;
use app\models\MediaFile;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class SettingsColorController extends SettingsBaseController
{
    public function actionIndex(): string
    {
        $colors = CatalogColor::find()
            ->with('swatchMedia')
            ->orderBy(['sort_order' => SORT_ASC, 'label' => SORT_ASC, 'id' => SORT_ASC])
            ->all();

        return $this->render('color/index', ['colors' => $colors]);
    }

    public function actionCreate(): Response|string
    {
        $model = new CatalogColor(['is_active' => true, 'sort_order' => 0]);

        if ($this->trySaveModel($model)) {
            \Yii::$app->session->setFlash('success', 'Цвет создан.');
            return $this->redirect(['index']);
        }

        return $this->render('color/form', [
            'model' => $model,
            'title' => 'Новый цвет',
        ]);
    }

    public function actionUpdate(int $id): Response|string
    {
        $model = $this->findModel($id);

        if ($this->trySaveModel($model)) {
            \Yii::$app->session->setFlash('success', 'Цвет обновлён.');
            return $this->redirect(['index']);
        }

        $model = CatalogColor::find()
            ->where(['id' => $id])
            ->with(['colorImages.media', 'swatchMedia'])
            ->one() ?? $model;

        return $this->render('color/form', [
            'model' => $model,
            'title' => 'Редактирование цвета',
        ]);
    }

    public function actionDelete(int $id): Response
    {
        $model = $this->findModel($id);
        $linkCount = CatalogFabricColor::find()->where(['color_id' => $model->id])->count();
        if ($linkCount > 0) {
            \Yii::$app->session->setFlash(
                'error',
                'Цвет используется в ' . $linkCount . ' фактурах. Сначала уберите его из коллекций тканей.'
            );

            return $this->redirect(['index']);
        }

        $model->delete();
        \Yii::$app->session->setFlash('success', 'Цвет удалён.');

        return $this->redirect(['index']);
    }

    public function actionGalleryAdd(int $id): array
    {
        \Yii::$app->response->format = Response::FORMAT_JSON;
        $color = $this->findModel($id);
        $mediaId = (int)\Yii::$app->request->post('mediaId', 0);
        $media = MediaFile::findOne($mediaId);
        if ($media === null) {
            \Yii::$app->response->statusCode = 400;

            return ['message' => 'Файл не найден.'];
        }

        if (CatalogColorImage::find()->where(['color_id' => $color->id, 'media_file_id' => $mediaId])->exists()) {
            \Yii::$app->response->statusCode = 400;

            return ['message' => 'Это фото уже добавлено.'];
        }

        $sortOrder = (int)CatalogColorImage::find()->where(['color_id' => $color->id])->count();
        $link = new CatalogColorImage([
            'color_id' => $color->id,
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

    public function actionGalleryDelete(int $id): array
    {
        \Yii::$app->response->format = Response::FORMAT_JSON;
        $color = $this->findModel($id);
        $linkId = (int)\Yii::$app->request->post('linkId', 0);
        $link = CatalogColorImage::findOne(['id' => $linkId, 'color_id' => $color->id]);
        if ($link === null) {
            \Yii::$app->response->statusCode = 404;

            return ['message' => 'Фото не найдено.'];
        }

        $link->delete();

        return ['ok' => true];
    }

    public function actionGalleryReorder(int $id): array
    {
        \Yii::$app->response->format = Response::FORMAT_JSON;
        $color = $this->findModel($id);
        $linkIds = array_values(array_filter(array_map('intval', (array)\Yii::$app->request->post('linkIds', []))));

        if ($linkIds === []) {
            \Yii::$app->response->statusCode = 400;

            return ['message' => 'Не передан порядок фото.'];
        }

        $links = CatalogColorImage::find()
            ->where(['color_id' => $color->id])
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

    private function findModel(int $id): CatalogColor
    {
        $model = CatalogColor::findOne($id);
        if ($model === null) {
            throw new NotFoundHttpException('Цвет не найден.');
        }

        return $model;
    }
}
