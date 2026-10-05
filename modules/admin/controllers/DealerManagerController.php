<?php

namespace app\modules\admin\controllers;

use app\models\DealerManager;
use app\models\DealerProfile;
use app\modules\admin\helpers\DealerManagerWorkHoursHelper;
use Yii;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class DealerManagerController extends BaseController
{
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $this->requirePermission('users');

        return true;
    }

    public function actionIndex(): Response
    {
        return $this->redirect(['/admin/user/index', 'tab' => UserController::TAB_MANAGERS]);
    }

    public function actionCreate(): Response|string
    {
        $model = new DealerManager(['is_active' => true]);

        if ($model->load(Yii::$app->request->post())) {
            $this->applyWorkHoursFromPost($model);
            if ($model->save()) {
                Yii::$app->session->setFlash('success', 'Менеджер создан.');

                return $this->redirect($this->managersListUrl());
            }
        }

        return $this->render('form', [
            'model' => $model,
            'title' => 'Новый менеджер',
        ]);
    }

    public function actionUpdate(int $id): Response|string
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post())) {
            $this->applyWorkHoursFromPost($model);
            if ($model->save()) {
                Yii::$app->session->setFlash('success', 'Менеджер обновлён.');

                return $this->redirect($this->managersListUrl());
            }
        }

        return $this->render('form', [
            'model' => $model,
            'title' => 'Редактирование менеджера',
        ]);
    }

    public function actionCreateAjax(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $model = new DealerManager(['is_active' => true]);
        if (!$model->load(Yii::$app->request->post())) {
            return ['ok' => false, 'errors' => ['form' => ['Некорректные данные.']]];
        }
        $this->applyWorkHoursFromPost($model);

        if (!$model->save()) {
            return ['ok' => false, 'errors' => $model->getErrors()];
        }

        return [
            'ok' => true,
            'manager' => [
                'id' => (int)$model->id,
                'name' => (string)$model->name,
            ],
        ];
    }

    public function actionDeactivate(int $id): Response
    {
        $model = $this->findModel($id);
        $assignedCount = DealerProfile::find()->where(['assigned_manager_id' => $model->id])->count();
        if ($assignedCount > 0) {
            $model->is_active = false;
            $model->save(false, ['is_active', 'updated_at']);
            Yii::$app->session->setFlash('success', 'Менеджер деактивирован (есть привязанные дилеры).');
        } else {
            $model->delete();
            Yii::$app->session->setFlash('success', 'Менеджер удалён.');
        }

        return $this->redirect($this->managersListUrl());
    }

    /**
     * @return array<int|string, int|string|null>
     */
    private function managersListUrl(): array
    {
        return ['/admin/user/index', 'tab' => UserController::TAB_MANAGERS];
    }

    private function findModel(int $id): DealerManager
    {
        $model = DealerManager::findOne($id);
        if ($model === null) {
            throw new NotFoundHttpException('Менеджер не найден.');
        }

        return $model;
    }

    private function applyWorkHoursFromPost(DealerManager $model): void
    {
        $workHours = DealerManagerWorkHoursHelper::formatFromPost(
            Yii::$app->request->post('WorkHours'),
        );
        if ($workHours !== null) {
            $model->work_hours = $workHours;
        }
    }
}
