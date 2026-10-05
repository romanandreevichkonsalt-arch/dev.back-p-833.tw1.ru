<?php

use app\models\AdminUser;
use app\models\Order;
use app\modules\admin\models\OrderForm;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var OrderForm $model */
/** @var AdminUser[] $managers */

$this->title = 'Новый заказ';
?>
<div class="admin-card">
    <?php $form = ActiveForm::begin(['options' => ['class' => 'admin-form']]); ?>

    <h2 style="margin:0 0 16px;font-size:18px;font-weight:500;">Клиент</h2>
    <div class="admin-detail-grid">
        <?= $form->field($model, 'customer_name') ?>
        <?= $form->field($model, 'customer_phone')->textInput(['placeholder' => '+79894232000']) ?>
        <?= $form->field($model, 'customer_email') ?>
        <?= $form->field($model, 'delivery_address') ?>
    </div>

    <?= $form->field($model, 'comment')->textarea(['rows' => 3]) ?>
    <?= $form->field($model, 'status')->dropDownList(Order::statusLabels()) ?>
    <?= $form->field($model, 'assigned_to')->dropDownList(
        ['' => 'Не назначен'] + ArrayHelper::map($managers, 'id', 'name')
    ) ?>
    <?= $form->field($model, 'manager_comment')->textarea(['rows' => 3]) ?>

    <h2 style="margin:24px 0 16px;font-size:18px;font-weight:500;">Позиции</h2>
    <?php foreach ($model->items as $index => $item): ?>
        <div class="admin-card" style="margin-bottom:12px;background:#faf9f7;">
            <div class="admin-detail-grid">
                <div class="form-group">
                    <label>Товар</label>
                    <input class="form-control" type="text" name="OrderForm[items][<?= $index ?>][product_title]" value="<?= Html::encode($item['product_title'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Артикул</label>
                    <input class="form-control" type="text" name="OrderForm[items][<?= $index ?>][product_sku]" value="<?= Html::encode($item['product_sku'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Кол-во</label>
                    <input class="form-control" type="number" min="1" name="OrderForm[items][<?= $index ?>][quantity]" value="<?= (int)($item['quantity'] ?? 1) ?>">
                </div>
                <div class="form-group">
                    <label>Цена, ₽</label>
                    <input class="form-control" type="number" min="0" step="0.01" name="OrderForm[items][<?= $index ?>][unit_price]" value="<?= Html::encode((string)($item['unit_price'] ?? 0)) ?>">
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <div class="admin-actions">
        <?= Html::submitButton('Создать заказ', ['class' => 'admin-btn']) ?>
        <?= Html::a('Отмена', ['index'], ['class' => 'admin-btn admin-btn--secondary']) ?>
    </div>

    <?php ActiveForm::end(); ?>
</div>
