<?php

/** @var yii\web\View $this */
/** @var app\models\Category $model */

use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

$parents = app\models\Category::find()->orderBy(['name' => SORT_ASC])->all();
$parentItems = ['' => '— Корневая —'];
foreach ($parents as $parent) {
    if (!$model->isNewRecord && (int) $parent->id === (int) $model->id) {
        continue;
    }
    $parentItems[$parent->id] = $parent->getDisplayLabel();
}
?>
<?php $form = ActiveForm::begin(['id' => 'category-form']); ?>
<div class="row">
    <div class="col-md-6">
        <?= $form->field($model, 'name')->textInput() ?>
        <?= $form->field($model, 'slug')->textInput(['placeholder' => 'divany-i-kresla'])->hint('Латиница, цифры и дефис. Если пусто — сгенерируется из названия.') ?>
        <?= $form->field($model, 'parent_id')->dropDownList($parentItems) ?>
        <?= $form->field($model, 'description')->textarea(['rows' => 4]) ?>
        <?= $form->field($model, 'is_active')->checkbox() ?>
        <?= $form->field($model, 'sort_order')->input('number') ?>
    </div>
    <div class="col-md-6">
        <?= $form->field($model, 'seo_title')->textInput() ?>
        <?= $form->field($model, 'seo_description')->textInput() ?>
        <?= $form->field($model, 'seo_h1')->textInput() ?>
    </div>
</div>
<div class="form-group mt-3">
    <?= Html::submitButton('Сохранить', ['class' => 'btn btn-success']) ?>
</div>
<?php ActiveForm::end(); ?>
