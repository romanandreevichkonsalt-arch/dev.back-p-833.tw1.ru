<?php

use app\models\CatalogBadge;
use app\modules\admin\helpers\AdminHtml;
use app\modules\admin\widgets\MediaPickerWidget;
use yii\helpers\Html;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var CatalogBadge $model */
/** @var string $title */

$this->title = $title;
?>
<?= $this->render('../_nav', ['active' => 'badge']) ?>

<div class="admin-card admin-card--full">
    <?php $form = ActiveForm::begin(['options' => ['class' => 'admin-form']]); ?>

    <div class="row g-4 align-items-start">
        <div class="col-12 col-md-7">
            <div class="admin-form-grid">
                <?= $form->field($model, 'label')->textInput() ?>
                <?= AdminHtml::slugField($form, $model, 'label') ?>
                <?= $form->field($model, 'variant')->dropDownList(CatalogBadge::variantLabels()) ?>
                <?= $form->field($model, 'sort_order')->input('number') ?>
                <?= $form->field($model, 'is_active')->checkbox() ?>
            </div>
        </div>
        <div class="col-12 col-md-5">
            <?= MediaPickerWidget::widget([
                'mode' => MediaPickerWidget::MODE_ID,
                'inputName' => Html::getInputName($model, 'image_id'),
                'value' => $model->image_id,
                'label' => $model->getAttributeLabel('image_id'),
            ]) ?>
        </div>
    </div>

    <div class="admin-actions">
        <?= Html::submitButton('Сохранить', ['class' => 'admin-btn']) ?>
        <?= Html::a('Отмена', ['index'], ['class' => 'admin-btn admin-btn--secondary']) ?>
    </div>

    <?php ActiveForm::end(); ?>
</div>
