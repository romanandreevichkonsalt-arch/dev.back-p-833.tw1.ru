<?php

/** @var yii\web\View $this */
/** @var app\models\User $user */
/** @var array<string,string> $roles */
/** @var string[] $selectedRoles */

use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

$this->title = 'Роли: ' . ($user->username ?: $user->phone);
?>
<h1><?= Html::encode($this->title) ?></h1>
<?php $form = ActiveForm::begin(); ?>
<?php foreach ($roles as $roleName => $label): ?>
    <div class="form-check">
        <?= Html::checkbox('roles[]', in_array($roleName, $selectedRoles, true), ['value' => $roleName, 'class' => 'form-check-input', 'id' => 'role-' . $roleName]) ?>
        <label class="form-check-label" for="role-<?= Html::encode($roleName) ?>"><?= Html::encode($label) ?></label>
    </div>
<?php endforeach; ?>
<div class="mt-3">
    <?= Html::submitButton('Сохранить', ['class' => 'btn btn-success']) ?>
    <?= Html::a('Назад', ['index'], ['class' => 'btn btn-secondary']) ?>
</div>
<?php ActiveForm::end(); ?>
