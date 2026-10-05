<?php

namespace app\modules\admin\controllers;

use app\models\CatalogBadge;
use app\models\CatalogProduct;
use app\modules\admin\helpers\ReferenceDeleteGuard;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class SettingsBadgeController extends SettingsBaseController
{
    public function actionIndex(): string
    {
        $badges = CatalogBadge::find()->with('image')->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC])->all();

        return $this->render('badge/index', ['badges' => $badges]);
    }

    public function actionCreate(): Response|string
    {
        $model = new CatalogBadge(['is_active' => true, 'sort_order' => 0, 'variant' => CatalogBadge::VARIANT_HIT]);

        if ($model->load(\Yii::$app->request->post()) && $model->save()) {
            \Yii::$app->session->setFlash('success', 'Бейдж создан.');
            return $this->redirect(['index']);
        }

        return $this->render('badge/form', ['model' => $model, 'title' => 'Новый бейдж']);
    }

    public function actionUpdate(int $id): Response|string
    {
        $model = $this->findModel($id);

        if ($model->load(\Yii::$app->request->post()) && $model->save()) {
            \Yii::$app->session->setFlash('success', 'Бейдж обновлён.');
            return $this->redirect(['index']);
        }

        return $this->render('badge/form', ['model' => $model, 'title' => 'Редактирование бейджа']);
    }

    public function actionDelete(int $id): Response
    {
        $model = $this->findModel($id);
        try {
            ReferenceDeleteGuard::ensureCanDelete([
                'есть товары с этим бейджем' => CatalogProduct::find()->where(['badge_id' => $model->id]),
            ]);
        } catch (BadRequestHttpException $e) {
            \Yii::$app->session->setFlash('error', $e->getMessage());
            return $this->redirect(['index']);
        }

        $model->delete();
        \Yii::$app->session->setFlash('success', 'Бейдж удалён.');

        return $this->redirect(['index']);
    }

    private function findModel(int $id): CatalogBadge
    {
        $model = CatalogBadge::findOne($id);
        if ($model === null) {
            throw new NotFoundHttpException('Бейдж не найден.');
        }

        return $model;
    }
}
