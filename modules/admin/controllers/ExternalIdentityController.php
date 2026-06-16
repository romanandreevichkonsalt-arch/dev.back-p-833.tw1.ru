<?php

namespace app\modules\admin\controllers;

use app\models\ExternalIdentity;
use app\services\RbacInitializer;
use yii\data\ActiveDataProvider;
use yii\web\NotFoundHttpException;

class ExternalIdentityController extends AdminController
{
    public function behaviors(): array
    {
        $behaviors = parent::behaviors();
        $behaviors['access'] = $this->permissionAccess(RbacInitializer::PERM_VIEW_EXTERNAL_IDENTITIES);

        return $behaviors;
    }

    public function actionIndex(): string
    {
        $dataProvider = new ActiveDataProvider([
            'query' => ExternalIdentity::find()->orderBy(['id' => SORT_DESC]),
            'pagination' => ['pageSize' => 30],
        ]);

        return $this->render('index', ['dataProvider' => $dataProvider]);
    }

    public function actionView(int $id): string
    {
        $model = ExternalIdentity::findOne($id);
        if ($model === null) {
            throw new NotFoundHttpException('Запись не найдена.');
        }

        return $this->render('view', ['model' => $model]);
    }
}
