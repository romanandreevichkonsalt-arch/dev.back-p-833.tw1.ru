<?php

use app\models\Order;
use app\modules\admin\helpers\AdminHtml;
use yii\helpers\Html;
use yii\grid\GridView;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var app\modules\admin\models\OrderSearch $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */

$this->title = 'Заказы';
?>
<div class="admin-toolbar">
    <div></div>
    <?= Html::a('Создать заказ', ['create'], ['class' => 'admin-btn']) ?>
</div>

<div class="admin-card" style="margin-bottom:16px;">
    <?php $form = ActiveForm::begin([
        'method' => 'get',
        'options' => ['class' => 'admin-form admin-filter-form'],
    ]); ?>
    <div class="admin-filter-grid">
        <?= $form->field($searchModel, 'status')->dropDownList(['' => 'Все статусы'] + Order::statusLabels()) ?>
        <?= $form->field($searchModel, 'q')->textInput(['placeholder' => 'Номер, клиент, телефон']) ?>
        <div class="admin-filter-actions">
            <?= Html::submitButton('Найти', ['class' => 'admin-btn']) ?>
            <?= Html::a('Сбросить', ['index'], ['class' => 'admin-btn admin-btn--secondary']) ?>
        </div>
    </div>
    <?php ActiveForm::end(); ?>
</div>

<div class="admin-card">
    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'tableOptions' => ['class' => 'admin-table'],
        'summary' => 'Показано {begin}–{end} из {totalCount}',
        'columns' => [
            'number',
            'customer_name',
            'customer_phone',
            [
                'attribute' => 'status',
                'format' => 'raw',
                'value' => static function (Order $model): string {
                    return Html::tag('span', Html::encode($model->getStatusLabel()), [
                        'class' => 'admin-badge admin-badge--' . Html::encode($model->status),
                    ]);
                },
            ],
            [
                'attribute' => 'total_amount',
                'value' => static fn (Order $model): string => $model->getFormattedTotal(),
            ],
            'created_at',
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
