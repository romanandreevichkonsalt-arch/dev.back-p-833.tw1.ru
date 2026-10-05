<?php

use app\models\User;
use app\modules\admin\helpers\AdminHtml;
use app\modules\admin\models\UserSearch;
use yii\grid\GridView;
use yii\helpers\Html;
use yii\widgets\ActiveForm;

/** @var yii\web\View $this */
/** @var UserSearch $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */
?>
<div class="admin-card" style="margin-bottom:16px;">
    <?php $form = ActiveForm::begin([
        'method' => 'get',
        'action' => ['index', 'tab' => 'customers'],
        'options' => ['class' => 'admin-form admin-filter-form'],
    ]); ?>
    <div class="admin-filter-grid">
        <?= $form->field($searchModel, 'q')->textInput(['placeholder' => 'Телефон, имя, email']) ?>
        <div class="admin-filter-actions">
            <?= Html::submitButton('Найти', ['class' => 'admin-btn']) ?>
            <?= Html::a('Сбросить', ['index', 'tab' => 'customers'], ['class' => 'admin-btn admin-btn--secondary']) ?>
        </div>
    </div>
    <?php ActiveForm::end(); ?>
</div>

<div class="admin-card">
    <p class="admin-hint">Клиенты создаются автоматически при оформлении заказа дилером.</p>
    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'tableOptions' => ['class' => 'admin-table'],
        'summary' => 'Показано {begin}–{end} из {totalCount}',
        'columns' => [
            'id',
            [
                'label' => 'Имя',
                'value' => static fn (User $model): string => $model->getDisplayName(),
            ],
            [
                'attribute' => 'phone',
                'value' => static fn (User $model): string => $model->getFormattedPhone(),
            ],
            [
                'label' => 'Email',
                'value' => static fn (User $model): ?string => $model->profile?->email,
            ],
            'created_at',
            [
                'class' => 'yii\grid\ActionColumn',
                'template' => '{view}',
                'contentOptions' => ['class' => 'admin-table-actions'],
                'headerOptions' => ['class' => 'admin-table-actions'],
                'buttons' => AdminHtml::gridActionButtons('{view}'),
            ],
        ],
    ]) ?>
</div>
