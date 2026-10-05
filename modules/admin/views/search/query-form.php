<?php

use app\models\SearchFrequentQuery;
use yii\helpers\Html;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var SearchFrequentQuery $model */
/** @var string $title */

$this->title = $title;
?>
<div class="admin-card" style="max-width:480px;">
    <?php $form = ActiveForm::begin(['options' => ['class' => 'admin-form']]); ?>

    <?= $form->field($model, 'query')->textInput() ?>
    <?= $form->field($model, 'sort_order')->input('number') ?>
    <?= $form->field($model, 'is_active')->checkbox() ?>

    <div class="admin-actions">
        <?= Html::submitButton('Сохранить', ['class' => 'admin-btn']) ?>
        <?= Html::a('Отмена', ['index'], ['class' => 'admin-btn admin-btn--secondary']) ?>
    </div>

    <?php ActiveForm::end(); ?>
</div>
