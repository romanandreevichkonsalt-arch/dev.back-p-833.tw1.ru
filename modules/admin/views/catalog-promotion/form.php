<?php

use app\models\CatalogPromotion;
use app\models\MediaFolder;
use app\modules\admin\assets\AdminAsset;
use app\modules\admin\helpers\PromoSectionTabs;
use app\modules\admin\models\CatalogPromotionForm;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var CatalogPromotionForm $model */
/** @var CatalogPromotion|null $promotion */
/** @var array<int, string> $models */

$this->title = $promotion === null ? 'Новая акция' : 'Акция: ' . $promotion->title;
$this->registerJsFile('@web/js/admin-home-products.js', ['depends' => [AdminAsset::class]]);
$this->registerJsFile('@web/js/admin-catalog-promotion.js', ['depends' => [AdminAsset::class]]);

$modelFieldId = Html::getInputId($model, 'catalog_model_id');
$scopeIsProduct = $model->scope_type === CatalogPromotion::SCOPE_PRODUCT;
$productFieldId = Html::getInputId($model, 'catalog_product_id');
$discountHintPercent = 'Процент скидки от розничной цены для всех дилеров.';
$discountHintFixed = 'Сумма, которая вычитается из розничной цены для всех дилеров.';
$discountHintInitial = $model->discount_type === CatalogPromotion::DISCOUNT_FIXED
    ? $discountHintFixed
    : $discountHintPercent;
?>
<div class="admin-toolbar">
    <?= Html::a('← Акции', ['/admin/promo-code/index', 'tab' => PromoSectionTabs::TAB_SALES], ['class' => 'admin-link']) ?>
</div>

<div class="admin-card">
    <?php $form = ActiveForm::begin([
        'options' => [
            'class' => 'admin-form',
            'data-catalog-promotion-form' => '1',
            'data-discount-hint-percent' => $discountHintPercent,
            'data-discount-hint-fixed' => $discountHintFixed,
        ],
    ]); ?>
    <div class="admin-form-grid admin-form-grid--catalog-promotion-head">
        <p
            class="admin-catalog-promotion-discount-hint"
            data-catalog-promotion-discount-hint
        ><?= Html::encode($discountHintInitial) ?></p>
        <?= $form->field($model, 'title', [
            'options' => ['class' => 'form-group admin-catalog-promotion-head-title'],
        ])->textInput() ?>
        <?= $form->field($model, 'discount_type', [
            'options' => ['class' => 'form-group admin-catalog-promotion-head-discount-type'],
        ])->dropDownList(
            CatalogPromotion::discountTypeLabels(),
            ['data-catalog-promotion-discount-type' => true],
        ) ?>
        <?= $form->field($model, 'discount_value', [
            'options' => ['class' => 'form-group admin-catalog-promotion-head-discount-value'],
            'enableClientValidation' => false,
        ])->input('number', ['step' => '0.01', 'min' => 0]) ?>
    </div>
    <div class="admin-form-grid">
        <?= $form->field($model, 'starts_at')->input('datetime-local') ?>
        <?= $form->field($model, 'ends_at')->input('datetime-local') ?>
        <?= $form->field($model, 'scope_type')->dropDownList(CatalogPromotion::scopeTypeLabels(), [
            'data-catalog-promotion-scope' => true,
        ]) ?>
        <?= $form->field($model, 'catalog_model_id')->dropDownList($models, [
            'prompt' => 'Выберите модель',
            'data-catalog-promotion-model' => true,
        ]) ?>
        <div
            class="form-group field-<?= Html::encode($productFieldId) ?> admin-catalog-promotion-product-wrap"
            data-catalog-promotion-product-wrap
            <?= $scopeIsProduct ? '' : 'hidden' ?>
        >
            <label class="form-label" for="<?= Html::encode($productFieldId) ?>-search">
                <?= Html::encode($model->getAttributeLabel('catalog_product_id')) ?>
            </label>
            <div
                class="admin-home-product-search"
                data-product-picker
                data-search-url="<?= Html::encode(Url::to(['/admin/catalog-promotion/search-products'])) ?>"
                data-model-id-selector="#<?= Html::encode($modelFieldId) ?>"
            >
                <?= Html::activeHiddenInput($model, 'catalog_product_id', ['data-product-id-input' => true]) ?>
                <input
                    type="text"
                    id="<?= Html::encode($productFieldId) ?>-search"
                    class="form-control"
                    value="<?= Html::encode($model->product_search) ?>"
                    placeholder="Минимум 3 символа: SKU, ткань, цвет…"
                    autocomplete="off"
                    data-product-search-input
                >
                <div class="admin-home-product-search__results" data-product-search-results hidden></div>
            </div>
            <?= Html::error($model, 'catalog_product_id', ['class' => 'help-block help-block-error']) ?>
        </div>
    </div>
    <div class="admin-form-section">
        <?= $this->render('@app/modules/admin/views/shared/_media_picker', [
            'inputName' => 'CatalogPromotionForm[image_media_id]',
            'value' => (string)($model->image_media_id ?? ''),
            'altValue' => '',
            'label' => 'Изображение акции',
            'defaultFolder' => MediaFolder::SLUG_BANNERS,
        ]) ?>
    </div>
    <div class="admin-form-actions">
        <?= Html::submitButton($promotion === null ? 'Создать' : 'Сохранить', ['class' => 'admin-btn']) ?>
        <?php if ($promotion !== null): ?>
            <?= Html::a('Удалить', ['delete', 'id' => $promotion->id], [
                'class' => 'admin-btn admin-btn--danger',
                'data' => ['method' => 'post', 'confirm' => 'Удалить акцию?'],
            ]) ?>
        <?php endif; ?>
    </div>
    <?php ActiveForm::end(); ?>
</div>
