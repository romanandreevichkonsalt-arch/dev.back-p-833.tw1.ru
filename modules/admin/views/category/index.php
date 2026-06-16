<?php

/** @var yii\web\View $this */
/** @var app\models\search\CategorySearch $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */

use yii\grid\GridView;
use yii\bootstrap5\Html;

$this->title = 'Категории';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="mb-0"><?= Html::encode($this->title) ?></h1>
    <div>
        <?= Html::a('Дерево', ['tree'], ['class' => 'btn btn-outline-secondary']) ?>
        <?= Html::a('Создать', ['create'], ['class' => 'btn btn-primary']) ?>
    </div>
</div>
<?= GridView::widget([
    'dataProvider' => $dataProvider,
    'filterModel' => $searchModel,
    'columns' => [
        'id',
        'name',
        'slug',
        'parent_id',
        'is_active:boolean',
        'sort_order',
        [
            'class' => 'yii\grid\ActionColumn',
            'template' => '{view} {update} {delete}',
        ],
    ],
]) ?>
