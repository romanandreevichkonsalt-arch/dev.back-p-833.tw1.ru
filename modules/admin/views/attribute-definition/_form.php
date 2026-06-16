<?php

/** @var yii\web\View $this */
/** @var app\models\AttributeDefinition $model */

use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

$types = [
    app\models\AttributeDefinition::TYPE_STRING => 'Строка',
    app\models\AttributeDefinition::TYPE_NUMBER => 'Число',
    app\models\AttributeDefinition::TYPE_BOOL => 'Да/Нет',
];
?>
<?php $form = ActiveForm::begin(); ?>
<?= $form->field($model, 'code')->textInput(!$model->isNewRecord ? ['readonly' => true] : []) ?>
<?= $form->field($model, 'name')->textInput() ?>
<?= $form->field($model, 'type')->dropDownList($types) ?>
<?= $form->field($model, 'is_filterable')->checkbox() ?>
<?= $form->field($model, 'is_required')->checkbox() ?>
<?= Html::submitButton('Сохранить', ['class' => 'btn btn-success']) ?>
<?php ActiveForm::end(); ?>
