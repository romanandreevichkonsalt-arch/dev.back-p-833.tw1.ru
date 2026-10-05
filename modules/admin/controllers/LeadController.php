<?php

namespace app\modules\admin\controllers;

use app\models\AdminUser;
use app\models\Lead;
use app\modules\admin\models\LeadSearch;
use app\services\lead\LeadAttachmentUploadService;
use Yii;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\UploadedFile;

class LeadController extends BaseController
{
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $this->requirePermission('leads');

        return true;
    }

    public function actionIndex(): string
    {
        $searchModel = new LeadSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    public function actionView(int $id): string
    {
        $model = $this->findModel($id);

        return $this->render('view', [
            'model' => $model,
            'managers' => $this->getManagers(),
        ]);
    }

    public function actionUpdate(int $id): Response|string
    {
        $model = $this->findModel($id);
        $model->setScenario('admin');

        if ($model->load(Yii::$app->request->post())) {
            $upload = new LeadAttachmentUploadService();
            $attachment = UploadedFile::getInstanceByName('attachment');
            if ($attachment !== null) {
                $upload->deleteStoredFile($model->attachment_path);
                $saved = $upload->saveForLead($model, $attachment);
                $model->attachment_path = $saved['path'];
                $model->attachment_original_name = $saved['originalName'];
                $model->resume_name = $saved['originalName'];
            }

            if ($model->save()) {
                if (in_array($model->status, [Lead::STATUS_CLOSED, Lead::STATUS_REJECTED], true) && empty($model->processed_at)) {
                    $model->processed_at = date('Y-m-d H:i:s');
                    $model->save(false, ['processed_at']);
                }

                Yii::$app->session->setFlash('success', 'Заявка обновлена.');

                return $this->redirect(['view', 'id' => $model->id]);
            }
        }

        return $this->render('update', [
            'model' => $model,
            'managers' => $this->getManagers(),
        ]);
    }

    public function actionDownloadAttachment(int $id): Response
    {
        $model = $this->findModel($id);
        if (!$model->hasStoredAttachment()) {
            throw new NotFoundHttpException('Файл не найден.');
        }

        $upload = new LeadAttachmentUploadService();
        $path = $upload->resolveAbsolutePath((string)$model->attachment_path);
        if (!is_file($path)) {
            throw new NotFoundHttpException('Файл не найден.');
        }

        return Yii::$app->response->sendFile(
            $path,
            $model->getAttachmentDisplayName() ?: basename($path)
        );
    }

    private function findModel(int $id): Lead
    {
        $model = Lead::findOne($id);
        if ($model === null) {
            throw new NotFoundHttpException('Заявка не найдена.');
        }

        return $model;
    }

    /**
     * @return AdminUser[]
     */
    private function getManagers(): array
    {
        return AdminUser::find()
            ->where(['is_active' => true])
            ->andWhere(['role' => [AdminUser::ROLE_ADMIN, AdminUser::ROLE_MANAGER]])
            ->orderBy(['name' => SORT_ASC])
            ->all();
    }
}
