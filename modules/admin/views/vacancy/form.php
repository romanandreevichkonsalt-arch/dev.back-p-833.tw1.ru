<?php

use app\models\Vacancy;
use app\modules\admin\helpers\AdminHtml;
use yii\helpers\Html;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var Vacancy $model */
/** @var string $title */

$this->title = $title;
$this->params['breadcrumbs'][] = ['label' => 'Вакансии', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;

$requirements = $model->getRequirementsArray();
$conditions = $model->getConditionsArray();
if ($requirements === []) {
    $requirements = [''];
}
if ($conditions === []) {
    $conditions = [''];
}

$descriptionHint = Html::tag('span', 'Краткое описание под заголовком на странице вакансии', [
    'class' => 'admin-form-label-hint vacancy-form__label-hint',
]);
$slugAutoHint = Html::tag('span', 'Формируется автоматически из должности', [
    'class' => 'admin-form-label-hint',
]);
?>
<div class="vacancy-form admin-page-block-section">
    <?php $form = ActiveForm::begin(['options' => ['class' => 'vacancy-form__form']]); ?>

    <div class="vacancy-form__grid vacancy-form__grid--head">
        <?= $form->field($model, 'title')->textInput(['maxlength' => true]) ?>
        <?= AdminHtml::slugField($form, $model, 'title', [], [
            'readonly' => true,
            'placeholder' => 'Заполнится из должности',
        ])->label($model->getAttributeLabel('slug') . ' ' . $slugAutoHint, ['encode' => false]) ?>
        <?= $form->field($model, 'direction_id')->dropDownList(Vacancy::directionLabels(), ['prompt' => 'Выберите направление']) ?>
    </div>

    <div class="vacancy-form__grid vacancy-form__grid--meta">
        <?= $form->field($model, 'department')->textInput([
            'maxlength' => true,
            'placeholder' => 'Например: Цех корпусной мебели',
        ]) ?>
        <?= $form->field($model, 'schedule')->dropDownList(Vacancy::scheduleOptions()) ?>
        <?= $form->field($model, 'location')->textInput(['maxlength' => true]) ?>
        <?= $form->field($model, 'salary')->textInput([
            'maxlength' => true,
            'placeholder' => 'Например: от 100 000 ₽',
        ]) ?>
    </div>

    <?= $form->field($model, 'description')
        ->label($model->getAttributeLabel('description') . $descriptionHint, ['encode' => false])
        ->textarea(['rows' => 4]) ?>

    <div class="vacancy-form__lists">
        <div class="vacancy-form__list" data-repeatable>
            <div class="admin-content-repeatable__toolbar vacancy-form__list-toolbar">
                <label class="form-label vacancy-form__list-label">Требования</label>
                <?= AdminHtml::repeatableAddButton('Добавить пункт') ?>
            </div>
            <div data-repeatable-list>
                <?php foreach ($requirements as $line): ?>
                    <div class="vacancy-form__list-item" data-repeatable-item>
                        <input type="text" class="form-control" name="requirements[]" value="<?= Html::encode($line) ?>">
                        <?= AdminHtml::repeatableRemoveButton() ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <template data-repeatable-template>
                <div class="vacancy-form__list-item" data-repeatable-item>
                    <input type="text" class="form-control" name="requirements[]" value="">
                    <?= AdminHtml::repeatableRemoveButton() ?>
                </div>
            </template>
        </div>

        <div class="vacancy-form__list" data-repeatable>
            <div class="admin-content-repeatable__toolbar vacancy-form__list-toolbar">
                <label class="form-label vacancy-form__list-label">Условия</label>
                <?= AdminHtml::repeatableAddButton('Добавить пункт') ?>
            </div>
            <div data-repeatable-list>
                <?php foreach ($conditions as $line): ?>
                    <div class="vacancy-form__list-item" data-repeatable-item>
                        <input type="text" class="form-control" name="conditions[]" value="<?= Html::encode($line) ?>">
                        <?= AdminHtml::repeatableRemoveButton() ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <template data-repeatable-template>
                <div class="vacancy-form__list-item" data-repeatable-item>
                    <input type="text" class="form-control" name="conditions[]" value="">
                    <?= AdminHtml::repeatableRemoveButton() ?>
                </div>
            </template>
        </div>
    </div>

    <div class="admin-actions mt-4">
        <?= Html::submitButton('Сохранить', ['class' => 'admin-btn']) ?>
        <?= Html::a('К списку', ['index'], ['class' => 'admin-btn admin-btn--secondary']) ?>
    </div>

    <?php ActiveForm::end(); ?>
</div>
