<?php

namespace app\modules\admin\controllers;

use app\models\AdminUser;
use app\models\Order;
use app\models\OrderStatusLog;
use app\modules\admin\models\OrderForm;
use app\modules\admin\models\OrderSearch;
use app\services\dealer\CashbackService;
use app\services\order\OrderDocumentUploadService;
use Yii;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\UploadedFile;

class OrderController extends BaseController
{
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $this->requirePermission('orders');

        return true;
    }

    public function actionIndex(): string
    {
        $searchModel = new OrderSearch();
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

    public function actionDownloadAttachment(int $id): Response
    {
        $order = $this->findModel($id);
        if ($order->attachment_path === null || trim($order->attachment_path) === '') {
            throw new NotFoundHttpException('Файл не найден.');
        }

        $uploadService = new \app\services\order\OrderAttachmentUploadService();
        $path = $uploadService->resolveAbsolutePath($order->attachment_path);
        if (!is_file($path)) {
            throw new NotFoundHttpException('Файл не найден.');
        }

        return Yii::$app->response->sendFile(
            $path,
            $order->attachment_original_name ?: basename($order->attachment_path),
        );
    }

    public function actionDownloadItemAttachment(int $id, int $itemId): Response
    {
        $order = $this->findModel($id);
        $item = null;
        foreach ($order->items as $orderItem) {
            if ((int)$orderItem->id === $itemId) {
                $item = $orderItem;
                break;
            }
        }

        if ($item === null || $item->attachment_path === null || trim($item->attachment_path) === '') {
            throw new NotFoundHttpException('Файл не найден.');
        }

        $uploadService = new \app\services\order\OrderAttachmentUploadService();
        $path = $uploadService->resolveAbsolutePath($item->attachment_path);
        if (!is_file($path)) {
            throw new NotFoundHttpException('Файл не найден.');
        }

        return Yii::$app->response->sendFile(
            $path,
            $item->attachment_original_name ?: basename($item->attachment_path),
        );
    }

    public function actionUploadDocument(int $id): Response
    {
        $order = $this->findModel($id);
        $file = UploadedFile::getInstanceByName('document');
        $label = trim((string)Yii::$app->request->post('label', ''));

        if ($file === null) {
            Yii::$app->session->setFlash('error', 'Выберите файл.');
            return $this->redirect(['view', 'id' => $order->id]);
        }

        try {
            (new OrderDocumentUploadService())->saveForOrder($order, $file, $label);
            Yii::$app->session->setFlash('success', 'Документ загружен.');
        } catch (\Throwable $e) {
            Yii::$app->session->setFlash('error', $e->getMessage());
        }

        return $this->redirect(['view', 'id' => $order->id]);
    }

    public function actionDownloadDocument(int $id, int $documentId): Response
    {
        $order = $this->findModel($id);
        $document = null;
        foreach ($order->documents as $orderDocument) {
            if ((int)$orderDocument->id === $documentId) {
                $document = $orderDocument;
                break;
            }
        }

        if ($document === null) {
            throw new NotFoundHttpException('Документ не найден.');
        }

        $uploadService = new OrderDocumentUploadService();
        $path = $uploadService->resolveAbsolutePath($document->stored_path);
        if (!is_file($path)) {
            throw new NotFoundHttpException('Файл не найден.');
        }

        return Yii::$app->response->sendFile(
            $path,
            $document->original_name ?: basename($document->stored_path),
        );
    }

    public function actionCreate(): Response|string
    {
        $form = new OrderForm();
        $form->loadDefaultItems();

        if ($form->load(Yii::$app->request->post()) && ($order = $form->save()) !== null) {
            Yii::$app->session->setFlash('success', 'Заказ «' . $order->number . '» создан.');
            return $this->redirect(['view', 'id' => $order->id]);
        }

        return $this->render('create', [
            'model' => $form,
            'managers' => $this->getManagers(),
        ]);
    }

    public function actionUpdate(int $id): Response|string
    {
        $order = $this->findModel($id);
        $oldStatus = $order->status;

        if ($order->load(Yii::$app->request->post()) && $order->save()) {
            if ($oldStatus !== $order->status) {
                $log = new OrderStatusLog([
                    'order_id' => (int)$order->id,
                    'old_status' => $oldStatus,
                    'new_status' => $order->status,
                    'comment' => $order->manager_comment,
                    'admin_user_id' => Yii::$app->adminUser->id,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
                $log->save(false);

                if ($oldStatus !== Order::STATUS_CANCELLED && $order->status === Order::STATUS_CANCELLED) {
                    (new CashbackService())->handleOrderCancelled($order);
                }
            }

            Yii::$app->session->setFlash('success', 'Заказ обновлён.');
            return $this->redirect(['view', 'id' => $order->id]);
        }

        return $this->render('update', [
            'model' => $order,
            'managers' => $this->getManagers(),
        ]);
    }

    private function findModel(int $id): Order
    {
        $model = Order::find()->where(['id' => $id])->with(['items', 'statusLogs.adminUser', 'promoGrant', 'documents'])->one();
        if ($model === null) {
            throw new NotFoundHttpException('Заказ не найден.');
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
