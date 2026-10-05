<?php

namespace app\modules\admin\controllers;

use app\exceptions\ApiValidationException;
use app\models\PromoCodeTemplate;
use app\models\CatalogPromotion;
use app\models\PromotionBanner;
use app\models\PromotionPopup;
use app\modules\admin\models\PromotionPopupForm;
use app\services\promotion\PromotionPopupAdminService;
use app\modules\admin\helpers\PromoCodeConditions;
use app\modules\admin\helpers\PromoSectionTabs;
use app\modules\admin\helpers\PromoTemplateOptions;
use app\modules\admin\models\PromoCodeForm;
use app\modules\admin\models\PromotionBannerForm;
use app\services\dealer\DealerPromoService;
use app\services\promotion\PromotionBannerAdminService;
use Yii;
use yii\helpers\Url;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class PromoCodeController extends BaseController
{
    public function __construct(
        $id,
        $module,
        private readonly DealerPromoService $promoService = new DealerPromoService(),
        private readonly PromotionBannerAdminService $bannerService = new PromotionBannerAdminService(),
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

    public function actionIndex(): Response|string
    {
        $activeTab = PromoSectionTabs::resolve(Yii::$app->request->get('tab'));
        $bannerForm = $this->newBannerForm();
        $popupForm = $this->newPopupForm();
        $promoTemplates = PromoTemplateOptions::activeLabels();
        $openBannerCreateModal = false;
        $openPopupCreateModal = false;

        if ($activeTab === PromoSectionTabs::TAB_BANNERS && Yii::$app->request->isPost) {
            $post = Yii::$app->request->post();
            if (isset($post['PromotionPopupForm'])) {
                $popupForm = $this->newPopupForm();
                $openPopupCreateModal = true;
                if ($popupForm->load($post)) {
                    if ($popupForm->validate()) {
                        try {
                            $this->popupService->save($popupForm, null, $this->adminUserId());
                            Yii::$app->session->setFlash('success', 'Попап создан.');
                            return $this->redirect(['index', 'tab' => PromoSectionTabs::TAB_BANNERS]);
                        } catch (ApiValidationException $e) {
                            $this->applyFormErrors($popupForm, $e);
                        }
                    } else {
                        Yii::$app->session->setFlash('error', 'Исправьте ошибки в форме попапа (в т.ч. промокод).');
                    }
                }
            } elseif (isset($post['PromotionBannerForm'])) {
                $bannerForm = $this->newBannerForm();
                $openBannerCreateModal = true;
                if ($bannerForm->load($post)) {
                    if ($bannerForm->validate()) {
                        try {
                            $this->bannerService->save($bannerForm, null, $this->adminUserId());
                            Yii::$app->session->setFlash('success', 'Баннер создан.');
                            return $this->redirect(['index', 'tab' => PromoSectionTabs::TAB_BANNERS]);
                        } catch (ApiValidationException $e) {
                            $this->applyFormErrors($bannerForm, $e);
                        }
                    } else {
                        Yii::$app->session->setFlash('error', 'Исправьте ошибки в форме баннера (заголовок, даты, промокод).');
                    }
                }
            }
        }

        return $this->render('index', $this->indexViewParams($activeTab, [
            'bannerForm' => $bannerForm,
            'popupForm' => $popupForm,
            'promoTemplates' => $promoTemplates,
            'openBannerCreateModal' => $openBannerCreateModal,
            'openPopupCreateModal' => $openPopupCreateModal,
        ]));
    }

    /**
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function indexViewParams(string $activeTab, array $extra = []): array
    {
        return array_merge([
            'activeTab' => $activeTab,
            'templates' => PromoCodeTemplate::find()->orderBy(['type' => SORT_ASC, 'id' => SORT_ASC])->all(),
            'systemPromos' => PromoCodeConditions::systemDefinitions(),
            'banners' => PromotionBanner::find()
                ->with(['template', 'imageMedia'])
                ->orderBy(['id' => SORT_DESC])
                ->all(),
            'popups' => PromotionPopup::find()
                ->with(['template', 'imageMedia'])
                ->orderBy(['id' => SORT_DESC])
                ->all(),
            'popupForm' => $this->newPopupForm(),
            'promotions' => CatalogPromotion::find()
                ->with(['catalogModel', 'catalogProduct'])
                ->orderBy(['starts_at' => SORT_DESC, 'id' => SORT_DESC])
                ->all(),
            'promoCreateForm' => new PromoCodeForm(),
            'promoCreateConditionLines' => PromoCodeConditions::linesForType(PromoCodeTemplate::TYPE_CUSTOM),
            'openPromoCreateModal' => false,
            'openBannerCreateModal' => false,
            'openPopupCreateModal' => false,
            'bannerForm' => $this->newBannerForm(),
            'promoTemplates' => PromoTemplateOptions::activeLabels(),
        ], $extra);
    }

    private function newBannerForm(): PromotionBannerForm
    {
        $form = new PromotionBannerForm();
        $form->promo_mode = PromotionBannerForm::PROMO_NONE;
        $form->grant_to_all_dealers = true;

        return $form;
    }

    private function newPopupForm(): PromotionPopupForm
    {
        $form = new PromotionPopupForm();
        $form->promo_mode = PromotionPopupForm::PROMO_NONE;
        $form->grant_to_all_dealers = true;

        return $form;
    }

    private function adminUserId(): ?int
    {
        $identity = Yii::$app->adminUser->identity;

        return $identity !== null ? (int)$identity->id : null;
    }

    private function applyFormErrors(PromotionBannerForm|PromotionPopupForm $form, ApiValidationException $e): void
    {
        $fieldMap = ['code' => 'promo_code'];
        foreach ($e->errors as $field => $messages) {
            $form->addError($fieldMap[$field] ?? $field, implode(' ', $messages));
        }
        if ($e->errors === []) {
            Yii::$app->session->setFlash('error', $e->getMessage());
        }
    }

    public function actionCreate(): Response|string
    {
        if (Yii::$app->request->isGet) {
            return $this->redirect(['index', 'tab' => PromoSectionTabs::TAB_PROMO_CODES]);
        }

        $form = new PromoCodeForm();

        if ($form->load(Yii::$app->request->post()) && $form->validate()) {
            try {
                $this->promoService->createTemplate(
                    $form->code,
                    $form->title,
                    (float)$form->discount_percent,
                    $form->is_single_use,
                    $form->is_active,
                    $form->valid_until,
                );
                Yii::$app->session->setFlash('success', 'Промокод создан.');

                return $this->redirect(['index', 'tab' => PromoSectionTabs::TAB_PROMO_CODES]);
            } catch (ApiValidationException $e) {
                foreach ($e->errors as $field => $messages) {
                    $form->addError($field, implode(' ', $messages));
                }
                if ($e->errors === []) {
                    Yii::$app->session->setFlash('error', $e->getMessage());
                }
            }
        }

        return $this->render('index', $this->indexViewParams(PromoSectionTabs::TAB_PROMO_CODES, [
            'promoCreateForm' => $form,
            'openPromoCreateModal' => true,
        ]));
    }

    public function actionUpdate(int $id): Response|string
    {
        $template = PromoCodeTemplate::findOne($id);
        if ($template === null) {
            throw new NotFoundHttpException('Промокод не найден.');
        }

        $form = PromoCodeForm::fromTemplate($template);

        if ($form->load(Yii::$app->request->post()) && $form->validate()) {
            try {
                $this->promoService->updateTemplate($template, [
                    'title' => $form->title,
                    'discount_percent' => $form->discount_percent,
                    'is_single_use' => $form->is_single_use,
                    'is_active' => $form->is_active,
                    'valid_until' => $form->valid_until,
                ]);
                Yii::$app->session->setFlash('success', 'Промокод обновлён.');
                return $this->redirect(['index', 'tab' => PromoSectionTabs::TAB_PROMO_CODES]);
            } catch (ApiValidationException $e) {
                Yii::$app->session->setFlash('error', $e->getMessage());
            }
        }

        return $this->render('update', [
            'template' => $template,
            'model' => $form,
            'conditionLines' => PromoCodeConditions::linesForTemplate($template),
        ]);
    }

    public function actionGrantToAllDealers(int $id): Response
    {
        $template = PromoCodeTemplate::findOne($id);
        if ($template === null) {
            throw new NotFoundHttpException('Промокод не найден.');
        }
        if ($template->type !== PromoCodeTemplate::TYPE_NOVELTY) {
            Yii::$app->session->setFlash('error', 'Массовая выдача доступна только для промокодов новинки.');
            return $this->redirect(['update', 'id' => $id]);
        }

        $count = $this->promoService->grantNoveltyTemplateToAllDealers($template);
        if ($count > 0) {
            Yii::$app->session->setFlash('success', 'Промокод «' . $template->code . '» выдан ' . $count . ' дилерам.');
        } else {
            Yii::$app->session->setFlash('warning', 'Активный промокод «' . $template->code . '» уже есть у всех дилеров или нет подходящих получателей.');
        }

        return $this->redirect(['update', 'id' => $id]);
    }

    public function actionDelete(int $id): Response
    {
        $template = PromoCodeTemplate::findOne($id);
        if ($template === null) {
            throw new NotFoundHttpException('Промокод не найден.');
        }
        if ($template->type !== PromoCodeTemplate::TYPE_CUSTOM) {
            Yii::$app->session->setFlash('error', 'Системные промокоды нельзя удалить.');
            return $this->redirect(['index', 'tab' => PromoSectionTabs::TAB_PROMO_CODES]);
        }

        $code = $template->code;
        $template->delete();
        Yii::$app->session->setFlash('success', 'Промокод «' . $code . '» удалён.');

        return $this->redirect(['index', 'tab' => PromoSectionTabs::TAB_PROMO_CODES]);
    }
}
