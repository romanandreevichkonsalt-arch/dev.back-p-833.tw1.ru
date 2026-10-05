<?php

use app\models\DealerManager;
use app\models\DealerProfile;
use app\modules\admin\models\DealerForm;
use yii\helpers\Html;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var DealerForm $model */
/** @var DealerManager[] $managers */

$this->title = 'Новый дилер';
?>
<div class="admin-toolbar">
    <?= Html::a('← Дилеры', ['index', 'tab' => 'dealers'], ['class' => 'admin-link']) ?>
    <?= Html::a('Менеджеры', ['index', 'tab' => 'managers'], ['class' => 'admin-link']) ?>
</div>

<div class="admin-card">
    <?php $form = ActiveForm::begin(['options' => ['class' => 'admin-form']]); ?>
    <div class="admin-form-grid admin-form-grid--3col">
        <?= $form->field($model, 'company_name')->textInput(['autofocus' => true]) ?>
        <?= $form->field($model, 'manager_name')->textInput() ?>
        <?= $form->field($model, 'inn')->textInput(['maxlength' => 12, 'placeholder' => 'Необязательно']) ?>
    </div>
    <div class="admin-form-grid">
        <?= $this->render('_dealer_manager_assign', [
            'form' => $form,
            'model' => $model,
            'managers' => $managers,
        ]) ?>
        <?= $form->field($model, 'email')->input('email') ?>
        <?= $form->field($model, 'phone')->textInput(['placeholder' => '79998886644']) ?>
        <?= $form->field($model, 'dealer_type')->dropDownList(DealerProfile::typeLabels()) ?>
        <?= $form->field($model, 'send_email')->checkbox() ?>
    </div>
    <div class="admin-form-actions">
        <?= Html::submitButton('Создать', ['class' => 'admin-btn']) ?>
    </div>
    <?php ActiveForm::end(); ?>
</div>

<?= $this->render('_dealer_manager_create_modal') ?>

<p class="admin-hint">Логин и пароль генерируются автоматически. Самостоятельная регистрация на сайте недоступна.</p>
