<?php

namespace app\modules\admin\controllers;

use app\models\User;
use app\services\RbacInitializer;
use Yii;
use yii\data\ActiveDataProvider;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class RbacController extends AdminController
{
    public function behaviors(): array
    {
        $behaviors = parent::behaviors();
        $behaviors['access'] = $this->permissionAccess(RbacInitializer::PERM_MANAGE_ROLES);

        return $behaviors;
    }

    public function actionIndex(): string
    {
        $dataProvider = new ActiveDataProvider([
            'query' => User::find()->where(['not', ['password_hash' => null]])->andWhere(['<>', 'password_hash', ''])->orderBy(['id' => SORT_ASC]),
            'pagination' => false,
        ]);

        return $this->render('index', [
            'dataProvider' => $dataProvider,
            'roles' => RbacInitializer::roleLabels(),
        ]);
    }

    public function actionAssign(int $id): Response|string
    {
        $user = User::findOne($id);
        if ($user === null) {
            throw new NotFoundHttpException('Пользователь не найден.');
        }

        $auth = Yii::$app->authManager;
        $selectedRoles = array_keys($auth->getRolesByUser((string) $user->id));

        if (Yii::$app->request->isPost) {
            $auth->revokeAll((string) $user->id);
            $postedRoles = (array) Yii::$app->request->post('roles', []);
            foreach ($postedRoles as $roleName) {
                $role = $auth->getRole($roleName);
                if ($role !== null) {
                    $auth->assign($role, (string) $user->id);
                }
            }
            $this->flashSuccess('Роли обновлены.');

            return $this->redirect(['index']);
        }

        return $this->render('assign', [
            'user' => $user,
            'roles' => RbacInitializer::roleLabels(),
            'selectedRoles' => $selectedRoles,
        ]);
    }
}
