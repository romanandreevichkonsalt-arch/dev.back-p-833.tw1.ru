<?php

use app\models\Lead;
use app\modules\admin\helpers\AdminHtml;
use yii\helpers\Html;
use yii\grid\GridView;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var app\modules\admin\models\LeadSearch $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */

$this->title = 'Заявки';
?>
<div class="admin-card admin-filter-card">
    <?php $form = ActiveForm::begin([
        'method' => 'get',
        'options' => ['class' => 'admin-form admin-filter-form'],
    ]); ?>
    <div class="admin-filter-grid admin-filter-grid--leads">
        <?= $form->field($searchModel, 'type')->dropDownList(['' => 'Все типы'] + Lead::typeLabels()) ?>
        <?= $form->field($searchModel, 'status')->dropDownList(['' => 'Все статусы'] + Lead::statusLabels()) ?>
        <?= $form->field($searchModel, 'q')->textInput(['placeholder' => 'ФИО, телефон, email, студия, город']) ?>
        <div class="admin-filter-actions">
            <?= Html::submitButton('Найти', ['class' => 'admin-btn']) ?>
            <?= Html::a('Сбросить', ['index'], ['class' => 'admin-btn admin-btn--secondary']) ?>
        </div>
    </div>
    <?php ActiveForm::end(); ?>
</div>

<div class="admin-card admin-card--table-scroll">
    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'tableOptions' => ['class' => 'admin-table admin-table--leads'],
        'summary' => 'Показано {begin}–{end} из {totalCount}',
        'columns' => [
            [
                'attribute' => 'type',
                'label' => 'Тип',
                'value' => static fn (Lead $model): string => $model->getTypeLabel(),
            ],
            [
                'attribute' => 'name',
                'label' => 'ФИО',
            ],
            [
                'attribute' => 'email',
                'format' => 'email',
                'value' => static fn (Lead $model): string => $model->email ?: '—',
            ],
            [
                'attribute' => 'phone',
                'value' => static fn (Lead $model): string => $model->phone ?: '—',
            ],
            [
                'attribute' => 'created_at',
                'label' => 'Дата создания',
                'value' => static function (Lead $model): string {
                    if ($model->created_at === null || $model->created_at === '') {
                        return '—';
                    }
                    $timestamp = strtotime((string)$model->created_at);

                    return $timestamp !== false ? date('d.m.Y', $timestamp) : Html::encode($model->created_at);
                },
            ],
            [
                'attribute' => 'status',
                'label' => 'Статус',
                'format' => 'raw',
                'value' => static function (Lead $model): string {
                    return Html::tag('span', Html::encode($model->getStatusLabel()), [
                        'class' => 'admin-badge admin-badge--' . Html::encode($model->status),
                    ]);
                },
            ],
            [
                'label' => 'Ответственный',
                'value' => static fn (Lead $model): string => $model->assignee?->name ?? '—',
            ],
            [
                'class' => 'yii\grid\ActionColumn',
                'template' => '{view} {update}',
                'contentOptions' => ['class' => 'admin-table-actions'],
                'headerOptions' => ['class' => 'admin-table-actions'],
                'buttons' => AdminHtml::gridActionButtons('{view} {update}'),
            ],
        ],
    ]) ?>
</div>
