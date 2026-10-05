<?php

use app\models\PromoCodeTemplate;
use app\modules\admin\helpers\PromoSectionTabs;
use app\modules\admin\models\PromoCodeForm;
use yii\helpers\Html;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var PromoCodeTemplate $template */
/** @var PromoCodeForm $model */
/** @var list<string> $conditionLines */

$this->title = 'Промокод: ' . $template->code;
$isCustom = $template->type === PromoCodeTemplate::TYPE_CUSTOM;
$isNovelty = $template->type === PromoCodeTemplate::TYPE_NOVELTY;
?>
<div class="admin-toolbar">
    <?= Html::a('← Промо и акции', ['index', 'tab' => PromoSectionTabs::TAB_PROMO_CODES], ['class' => 'admin-link']) ?>
    <?php if ($isNovelty): ?>
        <?= Html::a(
            'Выдать всем дилерам',
            ['grant-to-all-dealers', 'id' => $template->id],
            [
                'class' => 'admin-btn admin-btn--secondary',
                'style' => 'margin-left:12px;',
                'data' => [
                    'method' => 'post',
                    'confirm' => 'Выдать промокод «' . $template->code . '» всем дилерам?',
                ],
            ]
        ) ?>
    <?php endif; ?>
</div>

<div class="admin-card">
    <?php $form = ActiveForm::begin(['options' => ['class' => 'admin-form']]); ?>
    <div class="admin-form-grid">
        <div class="admin-form-field">
            <label class="admin-detail-label">Код</label>
            <div><code><?= Html::encode($template->code) ?></code></div>
        </div>
        <div class="admin-form-field">
            <label class="admin-detail-label">Тип</label>
            <div><?= Html::encode($template->getTypeLabel()) ?></div>
        </div>

        <?php if ($isCustom): ?>
            <?= $form->field($model, 'title')->textInput() ?>
        <?php else: ?>
            <div class="admin-form-field">
                <label class="admin-detail-label">Название</label>
                <div><?= Html::encode($template->title) ?></div>
            </div>
        <?php endif; ?>

        <?= $form->field($model, 'discount_percent')->input('number', [
            'step' => '0.1',
            'min' => 0,
            'max' => 100,
        ]) ?>

        <?php if ($isCustom): ?>
            <?= $form->field($model, 'valid_until')->input('date') ?>
            <?= $form->field($model, 'is_single_use')->checkbox() ?>
        <?php else: ?>
            <div class="admin-form-field">
                <label class="admin-detail-label">Одноразовый</label>
                <div><?= $template->is_single_use ? 'Да' : 'Нет' ?></div>
            </div>
        <?php endif; ?>

        <?= $form->field($model, 'is_active')->checkbox() ?>
    </div>
    <div class="admin-form-actions">
        <?= Html::submitButton('Сохранить', ['class' => 'admin-btn']) ?>
    </div>
    <?php ActiveForm::end(); ?>
</div>

<?= $this->render('_conditions', ['lines' => $conditionLines]) ?>
