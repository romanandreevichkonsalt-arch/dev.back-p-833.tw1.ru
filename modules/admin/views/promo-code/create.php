<?php

use app\modules\admin\helpers\PromoSectionTabs;
use app\modules\admin\models\PromoCodeForm;
use yii\helpers\Html;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var PromoCodeForm $model */
/** @var list<string> $conditionLines */

$this->title = 'Новый промокод';
?>
<div class="admin-toolbar">
    <?= Html::a('← Промо и акции', ['index', 'tab' => PromoSectionTabs::TAB_PROMO_CODES], ['class' => 'admin-link']) ?>
</div>

<div class="admin-card">
    <?php $form = ActiveForm::begin(['options' => ['class' => 'admin-form']]); ?>
    <div class="admin-form-grid">
        <?= $form->field($model, 'code')->textInput([
            'placeholder' => 'PROMO2026',
            'style' => 'text-transform:uppercase',
        ]) ?>
        <?= $form->field($model, 'title')->textInput() ?>
        <?= $form->field($model, 'discount_percent')->input('number', [
            'step' => '0.1',
            'min' => 0,
            'max' => 100,
        ]) ?>
        <?= $form->field($model, 'valid_until')->input('date', [
            'min' => date('Y-m-d'),
        ]) ?>
        <?= $form->field($model, 'is_single_use')->checkbox() ?>
        <?= $form->field($model, 'is_active')->checkbox() ?>
    </div>
    <div class="admin-form-actions">
        <?= Html::submitButton('Создать', ['class' => 'admin-btn']) ?>
    </div>
    <?php ActiveForm::end(); ?>
</div>

<?= $this->render('_conditions', ['lines' => $conditionLines]) ?>
