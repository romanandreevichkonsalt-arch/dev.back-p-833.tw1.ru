<?php

use app\models\DealerManager;
use app\modules\admin\controllers\UserController;
use yii\helpers\Html;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var DealerManager $model */
/** @var string $title */

$this->title = $title;
?>
<div class="admin-toolbar">
    <?= Html::a('← Менеджеры', ['/admin/user/index', 'tab' => UserController::TAB_MANAGERS], ['class' => 'admin-link']) ?>
</div>

<div class="admin-card">
    <?php $form = ActiveForm::begin(['options' => ['class' => 'admin-form']]); ?>
    <div class="admin-form-grid">
        <?= $form->field($model, 'name')->textInput() ?>
        <?= $form->field($model, 'phone')->textInput() ?>
        <?= $form->field($model, 'email')->input('email') ?>
        <div class="admin-form-field admin-form-field--full">
            <?= $this->render('_work_hours_fields', [
                'workHours' => $model->work_hours,
                'fieldPrefix' => 'WorkHours',
                'idPrefix' => 'dealer-manager-work-hours',
            ]) ?>
        </div>
        <?= $form->field($model, 'role_label')->textInput(['placeholder' => 'Менеджер заказов']) ?>
        <?= $form->field($model, 'is_active')->checkbox() ?>
    </div>
    <div class="admin-form-actions">
        <?= Html::submitButton('Сохранить', ['class' => 'admin-btn']) ?>
    </div>
    <?php ActiveForm::end(); ?>
</div>
