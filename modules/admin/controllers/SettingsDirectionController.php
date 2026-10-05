<?php

namespace app\modules\admin\controllers;

use app\models\CatalogDirection;
use app\models\CatalogCollection;
use app\modules\admin\helpers\ReferenceDeleteGuard;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class SettingsDirectionController extends SettingsBaseController
{
    public function actionIndex(): string
    {
        $directions = CatalogDirection::find()->orderBy(['sort_order' => SORT_ASC])->all();

        return $this->render('direction/index', ['directions' => $directions]);
    }

    public function actionCreate(): Response|string
    {
        $model = new CatalogDirection(['is_active' => true, 'sort_order' => 0]);

        if ($this->trySaveModel($model)) {
            \Yii::$app->session->setFlash('success', 'Направление создано.');
            return $this->redirect(['index']);
        }

        return $this->render('direction/form', ['model' => $model, 'title' => 'Новое направление']);
    }

    public function actionUpdate(int $id): Response|string
    {
        $model = $this->findDirection($id);

        if ($this->trySaveModel($model)) {
            \Yii::$app->session->setFlash('success', 'Направление обновлено.');
            return $this->redirect(['index']);
        }

        return $this->render('direction/form', ['model' => $model, 'title' => 'Редактирование направления']);
    }

    public function actionDelete(int $id): Response
    {
        $model = $this->findDirection($id);
        try {
            ReferenceDeleteGuard::ensureCanDelete([
                'есть коллекции' => $model->getCollections(),
            ]);
        } catch (BadRequestHttpException $e) {
            \Yii::$app->session->setFlash('error', $e->getMessage());
            return $this->redirect(['index']);
        }

        $model->delete();
        \Yii::$app->session->setFlash('success', 'Направление удалено.');

        return $this->redirect(['index']);
    }

    private function findDirection(int $id): CatalogDirection
    {
        $model = CatalogDirection::findOne($id);
        if ($model === null) {
            throw new NotFoundHttpException('Направление не найдено.');
        }

        return $model;
    }
}
