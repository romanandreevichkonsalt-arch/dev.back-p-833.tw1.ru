<?php

namespace app\modules\admin\controllers;

use app\exceptions\ApiValidationException;
use app\models\PromotionPopup;
use app\modules\admin\helpers\PromoSectionTabs;
use app\modules\admin\helpers\PromoTemplateOptions;
use app\modules\admin\models\PromotionPopupForm;
use app\services\promotion\PromotionPopupAdminService;
use Yii;
use yii\helpers\Url;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class PromotionPopupController extends BaseController
{
    public function __construct(
        $id,
        $module,
        private readonly PromotionPopupAdminService $popupService = new PromotionPopupAdminService(),
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

    public function actionCreate(): Response
    {
        return $this->redirect(['/admin/promo-code/index', 'tab' => PromoSectionTabs::TAB_BANNERS]);
    }

    public function actionUpdate(int $id): Response|string
    {
        $popup = $this->findPopup($id);
        $form = PromotionPopupForm::fromPopup($popup);

        if ($form->load(Yii::$app->request->post()) && $form->validate()) {
            try {
                $this->popupService->save($form, $popup, $this->adminUserId());
                Yii::$app->session->setFlash('success', 'Попап сохранён.');
                return $this->redirect(['/admin/promo-code/index', 'tab' => PromoSectionTabs::TAB_BANNERS]);
            } catch (ApiValidationException $e) {
                $this->applyApiErrorsToForm($form, $e);
            }
        }

        return $this->render('@app/modules/admin/views/promotion-banner/popup-form', [
            'model' => $form,
            'popup' => $popup,
            'promoTemplates' => PromoTemplateOptions::activeLabels(),
        ]);
    }

    public function actionDelete(int $id): Response
    {
        $popup = $this->findPopup($id);
        $title = $popup->headline !== '' ? $popup->headline : ('Попап #' . $popup->id);
        $popup->delete();
        Yii::$app->session->setFlash('success', 'Попап «' . $title . '» удалён.');

        return $this->redirect(['/admin/promo-code/index', 'tab' => PromoSectionTabs::TAB_BANNERS]);
    }

    public function actionGrantPromo(int $id): Response
    {
        $popup = $this->findPopup($id);
        $count = $this->popupService->grantPromoToAllDealers($popup, $this->adminUserId());
        if ($count > 0) {
            Yii::$app->session->setFlash('success', 'Промокод выдан ' . $count . ' дилерам.');
        } else {
            Yii::$app->session->setFlash('warning', 'Нет новых выдач: промокод не привязан или уже есть у всех дилеров.');
        }

        return $this->redirect(['update', 'id' => $id]);
    }

    private function findPopup(int $id): PromotionPopup
    {
        $popup = PromotionPopup::find()->where(['id' => $id])->with('template')->one();
        if ($popup === null) {
            throw new NotFoundHttpException('Попап не найден.');
        }

        return $popup;
    }

    private function adminUserId(): ?int
    {
        $identity = Yii::$app->adminUser->identity;

        return $identity !== null ? (int)$identity->id : null;
    }

    private function applyApiErrorsToForm(PromotionPopupForm $form, ApiValidationException $e): void
    {
        $fieldMap = ['code' => 'promo_code'];
        foreach ($e->errors as $field => $messages) {
            $form->addError($fieldMap[$field] ?? $field, implode(' ', $messages));
        }
        if ($e->errors === []) {
            Yii::$app->session->setFlash('error', $e->getMessage());
        }
    }
}
