<?php

namespace app\modules\admin\controllers;

use app\models\AttributeDefinition;
use app\models\ProductAttribute;
use app\services\RbacInitializer;
use Yii;
use yii\data\ActiveDataProvider;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class AttributeDefinitionController extends AdminController
{
    public function behaviors(): array
    {
        $behaviors = parent::behaviors();
        $behaviors['access'] = $this->permissionAccess(RbacInitializer::PERM_MANAGE_ATTRIBUTES);

        return $behaviors;
    }

    public function actionIndex(): string
    {
        $dataProvider = new ActiveDataProvider([
            'query' => AttributeDefinition::find()->orderBy(['code' => SORT_ASC]),
            'pagination' => ['pageSize' => 20],
        ]);

        return $this->render('index', ['dataProvider' => $dataProvider]);
    }

    public function actionView(int $id): string
    {
        return $this->render('view', ['model' => $this->findModel($id)]);
    }

    public function actionCreate(): Response|string
    {
        $model = new AttributeDefinition();

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            $this->flashSuccess('Атрибут создан.');

            return $this->redirect(['view', 'id' => $model->id]);
        }

        return $this->render('create', ['model' => $model]);
    }

    public function actionUpdate(int $id): Response|string
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            $this->flashSuccess('Атрибут обновлён.');

            return $this->redirect(['view', 'id' => $model->id]);
        }

        return $this->render('update', ['model' => $model]);
    }

    public function actionDelete(int $id): Response
    {
        $model = $this->findModel($id);
        if (ProductAttribute::find()->where(['attribute_code' => $model->code])->exists()) {
            $this->flashError('Атрибут используется в товарах.');
        } else {
            $model->delete();
            $this->flashSuccess('Атрибут удалён.');
        }

        return $this->redirect(['index']);
    }

    protected function findModel(int $id): AttributeDefinition
    {
        $model = AttributeDefinition::findOne($id);
        if ($model === null) {
            throw new NotFoundHttpException('Атрибут не найден.');
        }

        return $model;
    }
}
