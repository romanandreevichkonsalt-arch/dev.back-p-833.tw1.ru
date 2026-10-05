<?php

namespace app\modules\admin\controllers;

use app\exceptions\ApiValidationException;
use app\models\PromotionBanner;
use app\modules\admin\helpers\PromoSectionTabs;
use app\modules\admin\helpers\PromoTemplateOptions;
use app\modules\admin\models\PromotionBannerForm;
use app\services\promotion\PromotionBannerAdminService;
use Yii;
use yii\helpers\Url;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class PromotionBannerController extends BaseController
{
    public function __construct(
        $id,
        $module,
        private readonly PromotionBannerAdminService $bannerService = new PromotionBannerAdminService(),
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
        $banner = $this->findBanner($id);
        $form = PromotionBannerForm::fromBanner($banner);

        if ($form->load(Yii::$app->request->post()) && $form->validate()) {
            try {
                $this->bannerService->save($form, $banner, $this->adminUserId());
                Yii::$app->session->setFlash('success', 'Баннер сохранён.');
                return $this->redirect(['/admin/promo-code/index', 'tab' => 'banners']);
            } catch (ApiValidationException $e) {
                $this->applyApiErrorsToForm($form, $e);
            }
        }

        return $this->render('form', [
            'model' => $form,
            'banner' => $banner,
            'promoTemplates' => PromoTemplateOptions::activeLabels(),
        ]);
    }

    public function actionDelete(int $id): Response
    {
        $banner = $this->findBanner($id);
        $title = $banner->headline;
        $banner->delete();
        Yii::$app->session->setFlash('success', 'Баннер «' . $title . '» удалён.');

        return $this->redirect(['/admin/promo-code/index', 'tab' => 'banners']);
    }

    public function actionGrantPromo(int $id): Response
    {
        $banner = $this->findBanner($id);
        $count = $this->bannerService->grantPromoToAllDealers($banner, $this->adminUserId());
        if ($count > 0) {
            Yii::$app->session->setFlash('success', 'Промокод выдан ' . $count . ' дилерам.');
        } else {
            Yii::$app->session->setFlash('warning', 'Нет новых выдач: промокод не привязан или уже есть у всех дилеров.');
        }

        return $this->redirect(['update', 'id' => $id]);
    }

    private function findBanner(int $id): PromotionBanner
    {
        $banner = PromotionBanner::find()->where(['id' => $id])->with('template')->one();
        if ($banner === null) {
            throw new NotFoundHttpException('Баннер не найден.');
        }

        return $banner;
    }

    private function adminUserId(): ?int
    {
        $identity = Yii::$app->adminUser->identity;

        return $identity !== null ? (int)$identity->id : null;
    }

    private function applyApiErrorsToForm(PromotionBannerForm $form, ApiValidationException $e): void
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
