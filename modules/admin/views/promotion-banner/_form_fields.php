<?php

use app\models\MediaFolder;
use app\modules\admin\helpers\PromoTemplateOptions;
use app\modules\admin\models\PromotionBannerForm;
use yii\helpers\Html;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var PromotionBannerForm $model */
/** @var app\models\PromotionBanner|null $banner */
/** @var string|null $formAction */
/** @var bool $embedInCreateModal */

$embedInCreateModal = !empty($embedInCreateModal);
$promoCreateLocked = $banner !== null && $banner->template_id !== null;
$promoTemplates = PromoTemplateOptions::labelsForForm(
    $model->template_id !== null ? (int)$model->template_id : null,
);
$templateDropdownPrompt = PromoTemplateOptions::dropdownPrompt($model);
$templateSelectOptions = [
    'prompt' => $templateDropdownPrompt,
    'data-banner-promo-template' => true,
    'data-prompt-default' => '— существующий —',
];
if (
    $model->promo_mode === PromotionBannerForm::PROMO_CREATE
    && trim($model->promo_code) !== ''
    && ($model->template_id === null || (int)$model->template_id <= 0)
) {
    $templateSelectOptions['class'] = 'admin-promo-template-select--pending';
}
$formAction = $formAction ?? null;
$formOptions = ['class' => 'admin-form', 'data-promo-link-form' => '1'];
if ($formAction !== null && $formAction !== '') {
    $formOptions['action'] = $formAction;
}
foreach (['promo_code', 'promo_title', 'template_id'] as $promoErrorAttr) {
    if ($model->hasErrors($promoErrorAttr)) {
        $formOptions['data-reopen-promo-modal'] = '1';
        break;
    }
}
if ($embedInCreateModal) {
    $formOptions['class'] .= ' admin-form--in-modal';
}
$promoSummary = 'Промокод не задан';
if ($model->promo_mode === PromotionBannerForm::PROMO_CREATE && $model->promo_code !== '') {
    $promoSummary = 'Новый промокод: ' . $model->promo_code . ' (' . $model->promo_discount_percent . '%)';
} elseif ($model->template_id !== null) {
    $promoSummary = 'Привязан: ' . ($promoTemplates[(int)$model->template_id] ?? ('#' . $model->template_id));
}
$formName = 'PromotionBannerForm';
?>

<?php $activeForm = ActiveForm::begin([
    'options' => $formOptions,
    'enableClientValidation' => false,
    'enableAjaxValidation' => false,
    'validateOnSubmit' => false,
    'validateOnChange' => false,
    'validateOnBlur' => false,
]); ?>

<?= $activeForm->errorSummary($model, ['class' => 'admin-form-errors']) ?>

<?php if (!$embedInCreateModal): ?>
<div class="admin-promo-type-head">
    <span class="admin-promo-type-badge admin-promo-type-badge--feed">Лента</span>
    <h2 class="admin-form-section-title admin-promo-type-head__title">Баннер в ленте акций</h2>
</div>
<p class="admin-hint admin-promo-type-head__hint">Карточка в блоке акций ЛКД (не всплывающее окно).</p>
<?php endif; ?>
<?php if ($embedInCreateModal): ?><div class="admin-modal__body"><?php endif; ?>
<div class="admin-form-grid admin-form-grid--promo-banner-head-row">
    <?= $activeForm->field($model, 'headline')->textInput() ?>
    <?= $activeForm->field($model, 'valid_from')->input('date') ?>
    <?= $activeForm->field($model, 'valid_to')->input('date') ?>
</div>
<div class="admin-promo-banner-split">
    <div class="admin-promo-banner-split__fields">
        <?= $activeForm->field($model, 'body_text')->textarea(['rows' => 3]) ?>
        <div class="admin-form-grid admin-form-grid--promo-banner-prices">
            <?= $activeForm->field($model, 'price_current')->textInput(['placeholder' => '159 800 ₽']) ?>
            <?= $activeForm->field($model, 'price_old')->textInput(['placeholder' => '320 000 ₽']) ?>
            <?= $activeForm->field($model, 'cta_label')->textInput() ?>
            <?= $activeForm->field($model, 'cta_url')->textInput(['placeholder' => 'https://… или /catalog/…']) ?>
        </div>
    </div>
    <div class="admin-promo-banner-split__media">
        <?= $this->render('@app/modules/admin/views/shared/_media_picker', [
            'inputName' => 'PromotionBannerForm[image_media_id]',
            'value' => (string)($model->image_media_id ?? ''),
            'altValue' => '',
            'label' => 'Изображение',
            'defaultFolder' => MediaFolder::SLUG_BANNERS,
        ]) ?>
    </div>
</div>

<h2 class="admin-form-section-title">Промокод</h2>
<?= Html::activeHiddenInput($model, 'promo_mode', ['data-banner-promo-mode' => true]) ?>
<div class="admin-form-grid admin-form-grid--promo-banner-promo-bar">
    <?= $activeForm->field($model, 'promo_label')->textInput() ?>
    <?= $activeForm->field($model, 'template_id')->dropDownList($promoTemplates, $templateSelectOptions) ?>
    <div class="form-group admin-promo-banner-form__promo-actions">
        <label class="form-label">&nbsp;</label>
        <button type="button" class="admin-btn admin-btn--secondary" data-banner-promo-modal-open>
            <?= $promoCreateLocked ? 'Изменить промокод' : 'Создать промокод' ?>
        </button>
    </div>
    <?= $this->render('@app/modules/admin/views/promotion-banner/_promo_post_fields', ['model' => $model]) ?>
</div>
<p class="<?= trim($model->promo_code) !== '' || $model->template_id !== null ? 'admin-promo-bar-summary' : 'admin-muted' ?>" data-banner-promo-summary><?= Html::encode($promoSummary) ?></p>
<?= $this->render('@app/modules/admin/views/promotion-banner/_promo_link_modal', [
    'model' => $model,
    'formName' => $formName,
    'promoCreateLocked' => $promoCreateLocked,
]) ?>
<?php if ($embedInCreateModal): ?></div><?php endif; ?>

<?php if ($embedInCreateModal): ?>
<div class="admin-modal__footer">
    <button type="button" class="admin-btn admin-btn--secondary" data-promo-banner-create-modal-close>Отмена</button>
    <?= Html::submitButton('Создать баннер', ['class' => 'admin-btn']) ?>
</div>
<?php else: ?>
<div class="admin-form-actions">
    <?= Html::submitButton($banner === null ? 'Создать баннер' : 'Сохранить', ['class' => 'admin-btn']) ?>
    <?php if ($banner !== null): ?>
        <?= Html::a('Удалить', ['/admin/promotion-banner/delete', 'id' => $banner->id], [
            'class' => 'admin-btn admin-btn--danger',
            'data' => ['method' => 'post', 'confirm' => 'Удалить баннер?'],
        ]) ?>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php ActiveForm::end(); ?>
