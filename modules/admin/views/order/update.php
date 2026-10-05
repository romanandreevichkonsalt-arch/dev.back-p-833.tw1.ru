<?php

use app\models\AdminUser;
use app\models\Order;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var Order $model */
/** @var AdminUser[] $managers */

$this->title = 'Заказ ' . $model->number;
?>
<div class="admin-card" style="max-width:640px;">
    <?php $form = ActiveForm::begin(['options' => ['class' => 'admin-form']]); ?>

    <?= $form->field($model, 'status')->dropDownList(Order::statusLabels()) ?>
    <?= $form->field($model, 'payment_method')->dropDownList(
        ['' => 'Не указан'] + \app\services\order\OrderPaymentMapper::methodLabels()
    ) ?>
    <?= $form->field($model, 'assigned_to')->dropDownList(
        ['' => 'Не назначен'] + ArrayHelper::map($managers, 'id', 'name')
    ) ?>
    <?= $form->field($model, 'manager_comment')->textarea(['rows' => 5]) ?>

    <div class="admin-actions">
        <?= Html::submitButton('Сохранить', ['class' => 'admin-btn']) ?>
        <?= Html::a('Отмена', ['view', 'id' => $model->id], ['class' => 'admin-btn admin-btn--secondary']) ?>
    </div>

    <?php ActiveForm::end(); ?>
</div>
