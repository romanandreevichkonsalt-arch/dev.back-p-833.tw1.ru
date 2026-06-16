<?php

namespace app\modules\admin\controllers;

use app\models\User;
use app\models\UserProfile;
use app\services\RbacInitializer;
use Yii;
use yii\data\ActiveDataProvider;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class UserController extends AdminController
{
    public function behaviors(): array
    {
        $behaviors = parent::behaviors();
        $behaviors['access'] = $this->permissionAccess(RbacInitializer::PERM_MANAGE_USERS);

        return $behaviors;
    }

    public function actionIndex(): string
    {
        $dataProvider = new ActiveDataProvider([
            'query' => User::find()->orderBy(['id' => SORT_DESC]),
            'pagination' => ['pageSize' => 20],
        ]);

        return $this->render('index', ['dataProvider' => $dataProvider]);
    }

    public function actionView(int $id): string
    {
        $model = $this->findModel($id);

        return $this->render('view', ['model' => $model]);
    }

    public function actionCreate(): Response|string
    {
        $model = new User();
        $profile = new UserProfile();

        if ($model->load(Yii::$app->request->post()) && $profile->load(Yii::$app->request->post())) {
            $password = (string) Yii::$app->request->post('password', '');
            if ($password !== '') {
                $model->setPassword($password);
                $model->generateAuthKey();
            }
            if ($model->save()) {
                $profile->user_id = (int) $model->id;
                $profile->save(false);
                $this->flashSuccess('Пользователь создан.');

                return $this->redirect(['view', 'id' => $model->id]);
            }
        }

        return $this->render('create', ['model' => $model, 'profile' => $profile]);
    }

    public function actionUpdate(int $id): Response|string
    {
        $model = $this->findModel($id);
        $profile = $model->profile ?? new UserProfile(['user_id' => $model->id]);

        if ($model->load(Yii::$app->request->post()) && $profile->load(Yii::$app->request->post())) {
            $password = (string) Yii::$app->request->post('password', '');
            if ($password !== '') {
                $model->setPassword($password);
            }
            if ($model->save()) {
                $profile->user_id = (int) $model->id;
                $profile->save(false);
                $this->flashSuccess('Пользователь обновлён.');

                return $this->redirect(['view', 'id' => $model->id]);
            }
        }

        return $this->render('update', ['model' => $model, 'profile' => $profile]);
    }

    protected function findModel(int $id): User
    {
        $model = User::find()->with(['profile'])->where(['id' => $id])->one();
        if ($model === null) {
            throw new NotFoundHttpException('Пользователь не найден.');
        }

        return $model;
    }
}
