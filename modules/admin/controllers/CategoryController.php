<?php

namespace app\modules\admin\controllers;

use app\models\Category;
use app\models\search\CategorySearch;
use app\services\CategoryTreeService;
use app\services\RbacInitializer;
use Yii;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class CategoryController extends AdminController
{
    public function behaviors(): array
    {
        $behaviors = parent::behaviors();
        $behaviors['access'] = $this->permissionAccess(RbacInitializer::PERM_MANAGE_CATEGORIES);

        return $behaviors;
    }

    public function actionIndex(): string
    {
        $searchModel = new CategorySearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    public function actionTree(): string
    {
        /** @var CategoryTreeService $service */
        $service = Yii::createObject(CategoryTreeService::class);
        $roots = $service->buildTree();

        return $this->render('tree', ['roots' => $roots]);
    }

    public function actionView(int $id): string
    {
        return $this->render('view', ['model' => $this->findModel($id)]);
    }

    public function actionCreate(): Response|string
    {
        $model = new Category();

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            $this->flashSuccess('Категория создана.');

            return $this->redirect(['view', 'id' => $model->id]);
        }

        return $this->render('create', ['model' => $model]);
    }

    public function actionUpdate(int $id): Response|string
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            $this->flashSuccess('Категория обновлена.');

            return $this->redirect(['view', 'id' => $model->id]);
        }

        return $this->render('update', ['model' => $model]);
    }

    public function actionDelete(int $id): Response
    {
        $model = $this->findModel($id);
        if ($model->hasChildren()) {
            $this->flashError('Нельзя удалить категорию с подкатегориями.');
        } elseif ($model->hasProducts()) {
            $this->flashError('Нельзя удалить категорию с товарами.');
        } else {
            $model->delete();
            $this->flashSuccess('Категория удалена.');
        }

        return $this->redirect(['index']);
    }

    public function actionRestore(int $id): Response
    {
        $model = Category::findWithDeleted()->andWhere(['id' => $id])->one();
        if ($model !== null) {
            /** @var \app\behaviors\SoftDeleteBehavior|null $behavior */
            $behavior = $model->getBehavior(\app\behaviors\SoftDeleteBehavior::class);
            if ($behavior !== null && $behavior->restore()) {
                $this->flashSuccess('Категория восстановлена.');
            }
        }

        return $this->redirect(['index']);
    }

    protected function findModel(int $id): Category
    {
        $model = Category::findOne($id);
        if ($model === null) {
            throw new NotFoundHttpException('Категория не найдена.');
        }

        return $model;
    }
}
