<?php

namespace app\modules\admin\controllers;

use app\exceptions\ApiValidationException;
use app\models\DealerActivityLog;
use app\models\DealerCredentialsLog;
use app\models\DealerManager;
use app\models\DealerPriceList;
use app\models\DealerProfile;
use app\models\MediaFile;
use app\models\MediaFolder;
use app\services\dealer\DealerPriceListService;
use app\services\media\LocalMediaStorage;
use app\services\media\MediaContentValidator;
use yii\web\UploadedFile;
use app\models\User;
use app\modules\admin\models\DealerForm;
use app\modules\admin\models\DealerSearch;
use app\modules\admin\models\UserSearch;
use app\models\DealerProgramSettings;
use app\services\dealer\CashbackService;
use app\services\dealer\DealerCashbackExpiryResolver;
use app\services\dealer\DealerRegistrationService;
use app\services\dealer\DealerPromoService;
use Yii;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class UserController extends BaseController
{
    public const TAB_DEALERS = 'dealers';
    public const TAB_CUSTOMERS = 'customers';
    public const TAB_MANAGERS = 'managers';

    public function __construct(
        $id,
        $module,
        private readonly DealerRegistrationService $dealerRegistration = new DealerRegistrationService(),
        private readonly DealerPromoService $promoService = new DealerPromoService(),
        private readonly CashbackService $cashbackService = new CashbackService(),
        private readonly DealerCashbackExpiryResolver $cashbackExpiry = new DealerCashbackExpiryResolver(),
        $config = [],
    ) {
        parent::__construct($id, $module, $config);
    }

    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $this->requirePermission('users');

        return true;
    }

    public function actionIndex(): string
    {
        $tab = (string)Yii::$app->request->get('tab', self::TAB_DEALERS);
        if (!in_array($tab, [self::TAB_DEALERS, self::TAB_CUSTOMERS, self::TAB_MANAGERS], true)) {
            $tab = self::TAB_DEALERS;
        }

        if ($tab === self::TAB_CUSTOMERS) {
            $searchModel = new UserSearch();
            $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

            return $this->render('index', [
                'activeTab' => $tab,
                'searchModel' => $searchModel,
                'dataProvider' => $dataProvider,
            ]);
        }

        if ($tab === self::TAB_MANAGERS) {
            return $this->render('index', [
                'activeTab' => $tab,
                'managers' => DealerManager::find()
                    ->orderBy(['is_active' => SORT_DESC, 'name' => SORT_ASC, 'id' => SORT_ASC])
                    ->all(),
            ]);
        }

        $searchModel = new DealerSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'activeTab' => $tab,
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
            'globalPriceList' => $this->findActiveGlobalPriceList(),
            'orderFormBlank' => $this->findActiveOrderFormBlank(),
            'dealerProgramSettings' => DealerProgramSettings::getSingleton(),
        ]);
    }

    public function actionSaveDealerProgramSettings(): Response
    {
        $days = (int)Yii::$app->request->post('cashback_default_expiry_days', 0);
        if ($days < 1 || $days > 3650) {
            Yii::$app->session->setFlash('error', 'Укажите срок кэшбека от 1 до 3650 дней.');
            return $this->redirect(['index', 'tab' => self::TAB_DEALERS]);
        }

        $this->cashbackExpiry->saveDefaultExpiryDays($days);
        Yii::$app->session->setFlash('success', 'Срок действия кэшбека по умолчанию сохранён.');

        return $this->redirect(['index', 'tab' => self::TAB_DEALERS]);
    }

    public function actionUploadGlobalPriceList(): Response
    {
        $uploadedFile = UploadedFile::getInstanceByName('price_list_file');
        if ($uploadedFile === null) {
            Yii::$app->session->setFlash('error', 'Выберите файл прайс-листа.');
            return $this->redirect(['index', 'tab' => self::TAB_DEALERS]);
        }

        $label = trim((string)Yii::$app->request->post('label', 'Общий прайс-лист'));
        if ($label === '') {
            $label = 'Общий прайс-лист';
        }

        try {
            $storage = new LocalMediaStorage();
            $media = $storage->upload(
                $uploadedFile,
                null,
                MediaFile::KIND_DOCUMENT,
                null,
                MediaFolder::SLUG_DOCUMENTS,
            );
            (new DealerPriceListService())->assignUploadedFile(
                DealerPriceList::SCOPE_GLOBAL,
                $media,
                $label,
                null,
                Yii::$app->adminUser->id ?? null,
            );
            Yii::$app->session->setFlash('success', 'Общий прайс-лист загружен.');
        } catch (\Throwable $e) {
            Yii::$app->session->setFlash('error', $e->getMessage());
        }

        return $this->redirect(['index', 'tab' => self::TAB_DEALERS]);
    }

    public function actionUploadOrderFormBlank(): Response
    {
        $uploadedFile = UploadedFile::getInstanceByName('order_form_file');
        if ($uploadedFile === null) {
            Yii::$app->session->setFlash('error', 'Выберите файл бланка заказа.');
            return $this->redirect(['index', 'tab' => self::TAB_DEALERS]);
        }

        $label = trim((string)Yii::$app->request->post('label', 'Бланк заказа'));
        if ($label === '') {
            $label = 'Бланк заказа';
        }

        try {
            $storage = new LocalMediaStorage();
            $media = $storage->upload(
                $uploadedFile,
                null,
                MediaFile::KIND_DOCUMENT,
                null,
                MediaFolder::SLUG_DOCUMENTS,
            );
            (new DealerPriceListService())->assignUploadedFile(
                DealerPriceList::SCOPE_ORDER_FORM,
                $media,
                $label,
                null,
                Yii::$app->adminUser->id ?? null,
            );
            Yii::$app->session->setFlash('success', 'Бланк заказа загружен.');
        } catch (\Throwable $e) {
            Yii::$app->session->setFlash('error', $e->getMessage());
        }

        return $this->redirect(['index', 'tab' => self::TAB_DEALERS]);
    }

    public function actionDownloadOrderFormBlank(): Response
    {
        $record = $this->findActiveOrderFormBlank();
        $file = $record?->mediaFile;
        if ($file === null) {
            throw new NotFoundHttpException('Бланк заказа не загружен.');
        }

        $path = MediaContentValidator::absolutePath($file);
        if (!is_file($path)) {
            throw new NotFoundHttpException('Файл не найден.');
        }

        return Yii::$app->response->sendFile($path, (string)$file->filename);
    }

    public function actionCreate(): Response|string
    {
        $form = new DealerForm();

        if ($form->load(Yii::$app->request->post()) && $form->validate()) {
            try {
                $managerName = trim((string)($form->manager_name ?? ''));
                $result = $this->dealerRegistration->create(
                    $form->company_name,
                    $form->inn,
                    $managerName !== '' ? $managerName : null,
                    $form->email,
                    $form->phone,
                    $form->send_email,
                    Yii::$app->adminUser->id ?? null,
                    $form->dealer_type,
                    $form->assigned_manager_id,
                );

                if ($form->send_email && $result['emailSent']) {
                    Yii::$app->session->setFlash('success', 'Дилер создан. Доступ отправлен на email.');
                } elseif ($form->send_email) {
                    Yii::$app->session->setFlash('warning', 'Дилер создан, но email не отправлен. Скопируйте доступ ниже.');
                } else {
                    Yii::$app->session->setFlash('success', 'Дилер создан. Скопируйте доступ ниже.');
                }

                $this->flashDealerAccessCopy($result['user'], $result['password']);

                return $this->redirectToDealersIndex();
            } catch (ApiValidationException $e) {
                foreach ($e->errors as $attribute => $messages) {
                    $formField = match ($attribute) {
                        'companyName' => 'company_name',
                        'managerName' => 'manager_name',
                        'assignedManagerId' => 'assigned_manager_id',
                        default => $attribute,
                    };
                    if ($form->hasProperty($formField)) {
                        $form->addError($formField, implode(' ', $messages));
                    } else {
                        $form->addError('company_name', implode(' ', $messages));
                    }
                }
            }
        }

        return $this->render('create', [
            'model' => $form,
            'managers' => $this->getAssignableManagers(),
        ]);
    }

    public function actionView(int $id): string
    {
        $model = $this->findModel($id);

        if ($model->isDealer()) {
            $activityLogs = DealerActivityLog::find()
                ->where(['user_id' => $id])
                ->orderBy(['id' => SORT_DESC])
                ->limit(50)
                ->all();
            $credentialsLogs = DealerCredentialsLog::find()
                ->where(['user_id' => $id])
                ->orderBy(['id' => SORT_DESC])
                ->limit(20)
                ->all();

            return $this->render('view-dealer', [
                'model' => $model,
                'activityLogs' => $activityLogs,
                'credentialsLogs' => $credentialsLogs,
            ]);
        }

        return $this->render('view-customer', ['model' => $model]);
    }

    public function actionUpdate(int $id): Response|string
    {
        $user = $this->findDealer($id);
        $profile = $user->dealerProfile;
        if ($profile === null) {
            throw new NotFoundHttpException('Профиль дилера не найден.');
        }

        $cashbackAccount = $this->cashbackService->ensureAccount((int)$user->id);

        if ($user->load(Yii::$app->request->post()) && $profile->load(Yii::$app->request->post())) {
            $transaction = Yii::$app->db->beginTransaction();
            try {
                if (!$profile->save()) {
                    throw new ApiValidationException('Ошибка сохранения профиля.', $profile->getErrors());
                }
                if (!$user->save()) {
                    throw new ApiValidationException('Ошибка сохранения пользователя.', $user->getErrors());
                }

                $postedBalance = Yii::$app->request->post('DealerCashbackAccount');
                if (is_array($postedBalance) && array_key_exists('balance', $postedBalance)) {
                    $this->cashbackService->setBalanceManual(
                        (int)$user->id,
                        (float)str_replace(',', '.', trim((string)$postedBalance['balance']))
                    );
                }

                $user->markProfileCompleteIfReady();
                $transaction->commit();
                Yii::$app->session->setFlash('success', 'Дилер обновлён.');
                return $this->redirect(['update', 'id' => $id]);
            } catch (ApiValidationException $e) {
                $transaction->rollBack();
                Yii::$app->session->setFlash('error', $e->getMessage());
            }
        }

        $personalPriceList = DealerPriceList::find()
            ->where([
                'scope' => DealerPriceList::SCOPE_DEALER,
                'dealer_user_id' => (int)$user->id,
                'is_active' => true,
            ])
            ->with('mediaFile')
            ->orderBy(['updated_at' => SORT_DESC])
            ->one();

        $cashbackAccount->refresh();

        return $this->render('update-dealer', [
            'user' => $user,
            'profile' => $profile,
            'cashbackAccount' => $cashbackAccount,
            'managers' => $this->getAssignableManagers(),
            'personalPriceList' => $personalPriceList,
            'promoGrants' => $this->promoService->listGrantsForDealer((int)$user->id),
            'grantableTemplates' => $this->promoService->listGrantableTemplates(),
        ]);
    }

    public function actionUploadPersonalPriceList(int $id): Response
    {
        $user = $this->findDealer($id);
        $uploadedFile = UploadedFile::getInstanceByName('price_list_file');
        if ($uploadedFile === null) {
            Yii::$app->session->setFlash('error', 'Выберите файл прайс-листа.');
            return $this->redirect(['update', 'id' => $id]);
        }

        $label = trim((string)Yii::$app->request->post('label', 'Индивидуальный прайс-лист'));
        if ($label === '') {
            $label = 'Индивидуальный прайс-лист';
        }

        try {
            $storage = new LocalMediaStorage();
            $media = $storage->upload(
                $uploadedFile,
                null,
                MediaFile::KIND_DOCUMENT,
                null,
                MediaFolder::SLUG_DOCUMENTS,
            );
            (new DealerPriceListService())->assignUploadedFile(
                DealerPriceList::SCOPE_DEALER,
                $media,
                $label,
                (int)$user->id,
                Yii::$app->adminUser->id ?? null,
            );
            Yii::$app->session->setFlash('success', 'Индивидуальный прайс-лист загружен.');
        } catch (\Throwable $e) {
            Yii::$app->session->setFlash('error', $e->getMessage());
        }

        return $this->redirect(['update', 'id' => $id]);
    }

    /**
     * @return DealerManager[]
     */
    private function getAssignableManagers(): array
    {
        return DealerManager::find()
            ->where(['is_active' => true])
            ->orderBy(['name' => SORT_ASC])
            ->all();
    }

    public function actionGrantPromo(int $id): Response
    {
        $user = $this->findDealer($id);
        $templateId = (int)Yii::$app->request->post('template_id', 0);

        try {
            $grant = $this->promoService->grantFromTemplate(
                $user,
                $templateId,
                Yii::$app->adminUser->id ?? null,
                true,
            );
            Yii::$app->session->setFlash('success', 'Промокод «' . $grant->code . '» выдан.');
        } catch (ApiValidationException $e) {
            Yii::$app->session->setFlash('error', $e->getMessage());
        }

        return $this->redirect(['update', 'id' => $id]);
    }

    public function actionRevokePromo(int $id, int $grantId): Response
    {
        $user = $this->findDealer($id);

        try {
            $this->promoService->revokeGrantFromDealer($user, $grantId);
            Yii::$app->session->setFlash('success', 'Промокод снят с дилера.');
        } catch (ApiValidationException $e) {
            Yii::$app->session->setFlash('error', $e->getMessage());
        }

        return $this->redirect(['update', 'id' => $id]);
    }

    public function actionBlock(int $id): Response
    {
        $user = $this->findDealer($id);
        $this->dealerRegistration->setBlocked($user, true);
        Yii::$app->session->setFlash('success', 'Дилер заблокирован.');

        return $this->redirectToDealersIndex();
    }

    public function actionUnblock(int $id): Response
    {
        $user = $this->findDealer($id);
        $this->dealerRegistration->setBlocked($user, false);
        Yii::$app->session->setFlash('success', 'Дилер разблокирован.');

        return $this->redirectToDealersIndex();
    }

    public function actionSendCredentials(int $id): Response
    {
        $user = $this->findDealer($id);

        try {
            $result = $this->dealerRegistration->resendCredentials($user, Yii::$app->adminUser->id ?? null);
            if ($result['emailSent']) {
                Yii::$app->session->setFlash('success', 'Новый пароль отправлен на email.');
            } else {
                Yii::$app->session->setFlash(
                    'warning',
                    'Пароль обновлён, но email не отправлен. Новый пароль: ' . $result['password']
                );
            }
        } catch (ApiValidationException $e) {
            Yii::$app->session->setFlash('error', $e->getMessage());
        }

        return $this->redirectToDealersIndex();
    }

    public function actionCopyAccess(int $id): Response
    {
        $user = $this->findDealer($id);

        try {
            $result = $this->dealerRegistration->resetPasswordForCopy($user);
            $this->flashDealerAccessCopy($user, $result['password']);
            Yii::$app->session->setFlash('success', 'Новый пароль сгенерирован. Скопируйте доступ ниже.');
        } catch (ApiValidationException $e) {
            Yii::$app->session->setFlash('error', $e->getMessage());
        }

        return $this->redirectToDealersIndex();
    }

    public function actionGrantExhibition(int $id): Response
    {
        $user = $this->findDealer($id);
        $this->promoService->grantExhibitionPromo(
            $user,
            \app\models\DealerPromoGrant::SOURCE_ADMIN,
            Yii::$app->adminUser->id ?? null,
            true
        );
        Yii::$app->session->setFlash('success', 'Промокод ВЫСТАВКА выдан повторно.');

        return $this->redirectToDealersIndex();
    }

    private function redirectToDealersIndex(): Response
    {
        return $this->redirect(['index', 'tab' => self::TAB_DEALERS]);
    }

    private function flashDealerAccessCopy(User $user, string $password): void
    {
        Yii::$app->session->setFlash('dealerAccessCopy', [
            'username' => (string)$user->username,
            'password' => $password,
            'cabinetUrl' => (string)(Yii::$app->params['dealerCabinetUrl'] ?? ''),
        ]);
    }

    private function findModel(int $id): User
    {
        $model = User::find()->where(['id' => $id])->with(['profile', 'dealerProfile'])->one();
        if ($model === null) {
            throw new NotFoundHttpException('Пользователь не найден.');
        }

        return $model;
    }

    private function findDealer(int $id): User
    {
        $model = $this->findModel($id);
        if (!$model->isDealer()) {
            throw new NotFoundHttpException('Дилер не найден.');
        }

        return $model;
    }

    private function findActiveGlobalPriceList(): ?DealerPriceList
    {
        return $this->findActiveScopedPriceList(DealerPriceList::SCOPE_GLOBAL);
    }

    private function findActiveOrderFormBlank(): ?DealerPriceList
    {
        return $this->findActiveScopedPriceList(DealerPriceList::SCOPE_ORDER_FORM);
    }

    private function findActiveScopedPriceList(string $scope): ?DealerPriceList
    {
        return DealerPriceList::find()
            ->where(['scope' => $scope, 'is_active' => true, 'dealer_user_id' => null])
            ->with('mediaFile')
            ->orderBy(['updated_at' => SORT_DESC])
            ->one();
    }
}
