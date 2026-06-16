<?php

namespace app\modules\admin\controllers;

use app\models\ApiAccessToken;
use app\services\RbacInitializer;
use Yii;
use yii\data\ActiveDataProvider;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class ApiAccessTokenController extends AdminController
{
    public function behaviors(): array
    {
        $behaviors = parent::behaviors();
        $behaviors['access'] = $this->permissionAccess(RbacInitializer::PERM_VIEW_TOKENS);

        return $behaviors;
    }

    public function actionIndex(): string
    {
        $dataProvider = new ActiveDataProvider([
            'query' => ApiAccessToken::find()->orderBy(['id' => SORT_DESC]),
            'pagination' => ['pageSize' => 30],
        ]);

        return $this->render('index', ['dataProvider' => $dataProvider]);
    }

    public function actionRevoke(int $id): Response
    {
        $model = ApiAccessToken::findOne($id);
        if ($model === null) {
            throw new NotFoundHttpException('Токен не найден.');
        }
        $model->revoked_at = date('Y-m-d H:i:s');
        $model->save(false);
        $this->flashSuccess('Токен отозван.');

        return $this->redirect(['index']);
    }
}
