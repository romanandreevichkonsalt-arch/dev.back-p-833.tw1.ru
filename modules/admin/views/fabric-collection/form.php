<?php

use app\models\CatalogFabricCollection;
use app\modules\admin\helpers\AdminHtml;
use app\modules\admin\widgets\FabricCollectionColorsWidget;
use yii\helpers\Html;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var CatalogFabricCollection $model */
/** @var string $title */
/** @var array<int, string> $priceCategoryOptions */
/** @var array<int, string> $priceCategoryLine1Options */
/** @var array<string, string> $materialKindOptions */

$this->title = $title;
?>
<div class="admin-card admin-card--full">
    <?php $form = ActiveForm::begin(['options' => ['class' => 'admin-form']]); ?>
    <?= $form->errorSummary($model, ['class' => 'admin-form-errors']) ?>

    <div class="admin-form-grid admin-form-grid--fabric-row-1">
        <?= $form->field($model, 'material_kind')->dropDownList($materialKindOptions) ?>
        <?= $form->field($model, 'name')->textInput() ?>
        <?= AdminHtml::slugField($form, $model, 'name') ?>
        <?= $form->field($model, 'texture')->textInput(['placeholder' => 'Велюр, Букле…', 'list' => 'fabric-texture-options']) ?>
        <datalist id="fabric-texture-options">
            <option value="Гладкая"></option>
            <option value="Велюр"></option>
            <option value="Букле"></option>
            <option value="Жаккард"></option>
            <option value="Шенилл"></option>
        </datalist>
    </div>

    <div class="admin-form-grid admin-form-grid--fabric-row-2">
        <?= $form->field($model, 'density_gsm')->input('number', ['min' => 0]) ?>
        <?= $form->field($model, 'roll_width_cm')->input('number', ['min' => 0]) ?>
        <?= $form->field($model, 'martindale')->input('number', ['min' => 0]) ?>
    </div>

    <div class="admin-form-grid admin-form-grid--fabric-row-3">
        <?= $form->field($model, 'price_category_id')->dropDownList($priceCategoryOptions) ?>
        <?= $form->field($model, 'price_category_line1_id')->dropDownList($priceCategoryLine1Options) ?>
        <?= $form->field($model, 'meter_price_display')->textInput(['placeholder' => '700 ₽/м']) ?>
    </div>

    <div class="admin-form-grid admin-form-grid--fabric-row-4">
        <?= $form->field($model, 'description')->textarea(['rows' => 4]) ?>
        <?= $form->field($model, 'composition')->textarea(['rows' => 4]) ?>
        <?= $form->field($model, 'care_instructions')->textarea(['rows' => 4]) ?>
    </div>

    <div class="admin-form-grid admin-form-grid--fabric-meta">
        <?= $form->field($model, 'is_active')->checkbox() ?>
    </div>

    <?php if ($model->isNewRecord): ?>
        <p class="admin-form-notice">Сохраните коллекцию, затем добавьте цвета.</p>
    <?php else: ?>
        <?= FabricCollectionColorsWidget::widget(['collection' => $model]) ?>
    <?php endif; ?>

    <div class="admin-actions">
        <?= Html::submitButton('Сохранить', ['class' => 'admin-btn']) ?>
        <?= Html::a('Отмена', ['index'], ['class' => 'admin-btn admin-btn--secondary']) ?>
    </div>

    <?php ActiveForm::end(); ?>
</div>
