<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var app\modules\admin\models\LoginForm $model */

$this->title = 'Вход';
?>
<div class="admin-login__brand">
    <h1>МФ Анна</h1>
    <p>Вход в панель управления</p>
</div>

<?php $form = ActiveForm::begin([
    'options' => ['class' => 'admin-form'],
    'fieldConfig' => [
        'template' => "{label}\n{input}\n{error}",
        'inputOptions' => ['class' => 'form-control'],
        'errorOptions' => ['class' => 'help-block', 'style' => 'color:#9b3b3b;font-size:13px;'],
    ],
]); ?>

<?= $form->field($model, 'username')->textInput(['autofocus' => true]) ?>
<?= $form->field($model, 'password')->passwordInput() ?>
<?= $form->field($model, 'rememberMe')->checkbox() ?>

<div class="admin-actions">
    <?= Html::submitButton('Войти', ['class' => 'admin-btn']) ?>
</div>

<?php ActiveForm::end(); ?>
