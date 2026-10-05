<?php

namespace app\modules\admin\controllers;

use app\models\CatalogCategory;
use app\models\CatalogSubcategory;
use app\modules\admin\helpers\ReferenceDeleteGuard;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class SettingsCategoryController extends SettingsBaseController
{
    public function actionIndex(): string
    {
        $categories = CatalogCategory::find()
            ->with(['subcategories'])
            ->orderBy(['sort_order' => SORT_ASC, 'label' => SORT_ASC, 'id' => SORT_ASC])
            ->all();

        return $this->render('category/index', ['categories' => $categories]);
    }

    public function actionCreate(): Response|string
    {
        $model = new CatalogCategory(['is_active' => true, 'sort_order' => 0]);

        if ($this->trySaveModel($model)) {
            \Yii::$app->session->setFlash('success', 'Категория создана.');
            return $this->redirect(['index']);
        }

        return $this->render('category/form', [
            'model' => $model,
            'title' => 'Новая категория',
        ]);
    }

    public function actionUpdate(int $id): Response|string
    {
        $model = $this->findCategory($id);

        if ($this->trySaveModel($model)) {
            \Yii::$app->session->setFlash('success', 'Категория обновлена.');
            return $this->redirect(['index']);
        }

        return $this->render('category/form', [
            'model' => $model,
            'title' => 'Редактирование категории',
        ]);
    }

    public function actionDelete(int $id): Response
    {
        $model = $this->findCategory($id);
        try {
            ReferenceDeleteGuard::ensureCanDelete([
                'есть подкатегории' => $model->getSubcategories(),
                'есть модели' => \app\models\CatalogModel::find()->where(['category_id' => $model->id]),
            ]);
        } catch (BadRequestHttpException $e) {
            \Yii::$app->session->setFlash('error', $e->getMessage());
            return $this->redirect(['index']);
        }

        $model->delete();
        \Yii::$app->session->setFlash('success', 'Категория удалена.');

        return $this->redirect(['index']);
    }

    public function actionCreateSubcategory(int $category_id): Response|string
    {
        $category = $this->findCategory($category_id);
        $model = new CatalogSubcategory(['category_id' => $category->id, 'is_active' => true, 'sort_order' => 0]);

        if ($model->load(\Yii::$app->request->post()) && $model->save()) {
            \Yii::$app->session->setFlash('success', 'Подкатегория создана.');
            return $this->redirect(['index']);
        }

        return $this->render('category/subcategory-form', [
            'model' => $model,
            'category' => $category,
            'title' => 'Новая подкатегория',
        ]);
    }

    public function actionUpdateSubcategory(int $id): Response|string
    {
        $model = $this->findSubcategory($id);

        if ($model->load(\Yii::$app->request->post()) && $model->save()) {
            \Yii::$app->session->setFlash('success', 'Подкатегория обновлена.');
            return $this->redirect(['index']);
        }

        return $this->render('category/subcategory-form', [
            'model' => $model,
            'category' => $model->category,
            'title' => 'Редактирование подкатегории',
        ]);
    }

    public function actionDeleteSubcategory(int $id): Response
    {
        $model = $this->findSubcategory($id);
        try {
            ReferenceDeleteGuard::ensureCanDelete([
                'есть товары' => $model->getProducts(),
                'есть модели' => \app\models\CatalogModel::find()->where(['subcategory_id' => $model->id]),
            ]);
        } catch (BadRequestHttpException $e) {
            \Yii::$app->session->setFlash('error', $e->getMessage());
            return $this->redirect(['index']);
        }

        $model->delete();
        \Yii::$app->session->setFlash('success', 'Подкатегория удалена.');

        return $this->redirect(['index']);
    }

    private function findCategory(int $id): CatalogCategory
    {
        $model = CatalogCategory::findOne($id);
        if ($model === null) {
            throw new NotFoundHttpException('Категория не найдена.');
        }

        return $model;
    }

    private function findSubcategory(int $id): CatalogSubcategory
    {
        $model = CatalogSubcategory::find()->where(['id' => $id])->with('category')->one();
        if ($model === null) {
            throw new NotFoundHttpException('Подкатегория не найдена.');
        }

        return $model;
    }
}
