<?php

use app\models\SearchCategory;
use app\modules\admin\helpers\AdminHtml;
use yii\helpers\Html;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var SearchCategory $model */
/** @var string $title */

$this->title = $title;
?>
<div class="admin-card" style="max-width:520px;">
    <?php $form = ActiveForm::begin(['options' => ['class' => 'admin-form']]); ?>

    <?= $form->field($model, 'label')->textInput() ?>
    <?= AdminHtml::slugField($form, $model, 'label') ?>
    <?= $form->field($model, 'icon')->textInput() ?>
    <?= $form->field($model, 'href')->textInput() ?>
    <?= $form->field($model, 'sort_order')->input('number') ?>
    <?= $form->field($model, 'is_active')->checkbox() ?>

    <div class="admin-actions">
        <?= Html::submitButton('Сохранить', ['class' => 'admin-btn']) ?>
        <?= Html::a('Отмена', ['index'], ['class' => 'admin-btn admin-btn--secondary']) ?>
    </div>

    <?php ActiveForm::end(); ?>
</div>
