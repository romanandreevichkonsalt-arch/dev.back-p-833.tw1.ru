<?php

/** @var yii\web\View $this */
/** @var app\models\User $model */
/** @var app\models\UserProfile $profile */

use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;
?>
<?php $form = ActiveForm::begin(); ?>
<div class="row">
    <div class="col-md-6">
        <?= $form->field($model, 'phone')->textInput() ?>
        <?= $form->field($model, 'username')->textInput() ?>
        <div class="mb-3">
            <label class="form-label">Пароль</label>
            <input type="password" name="password" class="form-control" <?= $model->isNewRecord ? 'required' : '' ?>>
            <?php if (!$model->isNewRecord): ?><div class="form-text">Оставьте пустым, чтобы не менять</div><?php endif; ?>
        </div>
    </div>
    <div class="col-md-6">
        <?= $form->field($profile, 'first_name')->textInput() ?>
        <?= $form->field($profile, 'last_name')->textInput() ?>
        <?= $form->field($profile, 'display_name')->textInput() ?>
        <?= $form->field($profile, 'email')->textInput() ?>
        <?= $form->field($profile, 'avatar_url')->textInput() ?>
        <?= $form->field($profile, 'yandex_login')->textInput() ?>
    </div>
</div>
<?= Html::submitButton('Сохранить', ['class' => 'btn btn-success']) ?>
<?php ActiveForm::end(); ?>
