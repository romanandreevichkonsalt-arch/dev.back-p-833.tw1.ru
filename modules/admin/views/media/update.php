<?php

use app\models\MediaFile;
use yii\helpers\Html;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var MediaFile $model */

$this->title = 'Редактирование файла';
?>
<div class="admin-card" style="max-width:560px;">
    <?php if (strpos($model->mime, 'image/') === 0): ?>
        <img src="<?= Html::encode($model->getPublicUrl()) ?>" alt="" style="max-width:100%;border-radius:8px;margin-bottom:16px;">
    <?php endif; ?>

    <p style="color:#6b6862;">URL: <code><?= Html::encode($model->getPublicUrl()) ?></code></p>

    <?php $form = ActiveForm::begin([
        'options' => ['class' => 'admin-form'],
        'fieldConfig' => [
            'inputOptions' => ['class' => 'form-control'],
        ],
    ]); ?>

    <?= $form->field($model, 'alt')->textInput(['maxlength' => true]) ?>

    <div class="admin-actions">
        <?= Html::submitButton('Сохранить', ['class' => 'admin-btn']) ?>
        <?= Html::a('Назад', ['index'], ['class' => 'admin-btn admin-btn--secondary']) ?>
    </div>

    <?php ActiveForm::end(); ?>
</div>
