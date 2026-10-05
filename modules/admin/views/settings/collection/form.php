<?php

use app\models\CatalogCollection;
use app\modules\admin\helpers\AdminHtml;
use app\modules\admin\widgets\CollectionGalleryWidget;
use yii\helpers\Html;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var CatalogCollection $model */
/** @var string $title */
/** @var array<int, string> $directions */
/** @var bool $hasDirections */

$this->title = $title;
$isNew = $model->isNewRecord;
?>
<?= $this->render('../_nav', ['active' => 'collection']) ?>

<div class="admin-card admin-card--full">
    <?php if (!$hasDirections): ?>
        <p class="admin-form-notice">Сначала создайте направление «А+» или «Линия 1» в разделе «Направления».</p>
    <?php endif; ?>

    <?php $form = ActiveForm::begin(['options' => ['class' => 'admin-form']]); ?>
    <?= $form->errorSummary($model, ['class' => 'admin-form-errors']) ?>

    <div class="row g-4 align-items-start">
        <div class="col-12 col-md-7">
            <h3 class="admin-form-section-title">Основное</h3>
            <div class="admin-form-grid">
                <?= $form->field($model, 'direction_id')->dropDownList($directions)->hint('Направление каталога: А+ или Линия 1') ?>
                <?= $form->field($model, 'name')->textInput(['placeholder' => 'Например: Артемида'])->hint('Название коллекции') ?>
                <?= AdminHtml::slugField($form, $model, 'name') ?>
                <?= $form->field($model, 'sort_order')->input('number') ?>
                <?= $form->field($model, 'is_active')->checkbox() ?>
            </div>

            <details class="admin-form-details" <?= $isNew ? '' : 'open' ?>>
                <summary class="admin-form-details__summary">Дополнительно (витрина, SEO)</summary>
                <div class="admin-form-grid admin-form-details__body">
                    <?= $form->field($model, 'label')->textInput() ?>
                    <?= $form->field($model, 'title')->textInput() ?>
                    <?= $form->field($model, 'href')->textInput() ?>
                    <?= $form->field($model, 'cta_label')->textInput() ?>
                    <?= $form->field($model, 'title_uppercase')->checkbox() ?>
                </div>
                <?= $form->field($model, 'description')->textarea(['rows' => 4]) ?>
            </details>
        </div>
        <div class="col-12 col-md-5">
            <h3 class="admin-form-section-title">Фото коллекции</h3>
            <div class="admin-product-form__gallery">
                <?= CollectionGalleryWidget::widget(['collection' => $model]) ?>
            </div>
        </div>
    </div>

    <div class="admin-actions">
        <?= Html::submitButton('Сохранить', ['class' => 'admin-btn']) ?>
        <?= Html::a('Отмена', ['index'], ['class' => 'admin-btn admin-btn--secondary']) ?>
    </div>

    <?php ActiveForm::end(); ?>
</div>
