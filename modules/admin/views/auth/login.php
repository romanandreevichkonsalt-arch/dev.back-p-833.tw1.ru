<?php

/** @var yii\web\View $this */
/** @var app\modules\admin\models\AdminLoginForm $model */

use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

$this->title = 'Вход в админку';
?>
<div class="card shadow-sm">
    <div class="card-body">
        <h1 class="h4 mb-3"><?= Html::encode($this->title) ?></h1>
        <?php $form = ActiveForm::begin(['id' => 'login-form']); ?>
        <?= $form->field($model, 'username')->textInput(['autofocus' => true]) ?>
        <?= $form->field($model, 'password')->passwordInput() ?>
        <?= $form->field($model, 'rememberMe')->checkbox() ?>
        <div class="d-grid">
            <?= Html::submitButton('Войти', ['class' => 'btn btn-primary']) ?>
        </div>
        <?php ActiveForm::end(); ?>
    </div>
</div>
