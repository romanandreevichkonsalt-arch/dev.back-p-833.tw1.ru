<?php

use app\modules\admin\models\PromoCodeForm;
use yii\helpers\Html;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var PromoCodeForm $model */
/** @var list<string> $conditionLines */
/** @var bool $open */

$open = !empty($open);
?>
<div
    class="admin-modal admin-promo-code-create-modal"
    data-promo-code-create-modal
    <?= $open ? '' : 'hidden' ?>
>
    <div class="admin-modal__backdrop" data-promo-code-create-modal-close></div>
    <div class="admin-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="promo-code-create-modal-title">
        <div class="admin-modal__header">
            <h3 class="admin-modal__title" id="promo-code-create-modal-title">Новый промокод</h3>
            <button type="button" class="admin-modal__close" data-promo-code-create-modal-close aria-label="Закрыть">&times;</button>
        </div>
        <?php $form = ActiveForm::begin([
            'action' => ['/admin/promo-code/create'],
            'options' => ['class' => 'admin-form'],
        ]); ?>
        <div class="admin-modal__body">
            <div class="admin-form-grid admin-form-grid--2col">
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
            <?php if ($conditionLines !== []): ?>
                <ul class="admin-promo-conditions__list admin-promo-conditions__list--compact admin-muted" style="margin-top:12px;">
                    <?php foreach ($conditionLines as $line): ?>
                        <li><?= Html::encode($line) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        <div class="admin-modal__footer">
            <button type="button" class="admin-btn admin-btn--secondary" data-promo-code-create-modal-close>Отмена</button>
            <?= Html::submitButton('Создать', ['class' => 'admin-btn']) ?>
        </div>
        <?php ActiveForm::end(); ?>
    </div>
</div>
