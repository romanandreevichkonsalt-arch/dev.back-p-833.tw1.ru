<?php

/** @var yii\web\View $this */
/** @var app\models\search\ProductSearch $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */

use yii\grid\GridView;
use yii\bootstrap5\Html;

$this->title = 'Товары';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="mb-0"><?= Html::encode($this->title) ?></h1>
    <?= Html::a('Создать', ['create'], ['class' => 'btn btn-primary']) ?>
</div>
<?= GridView::widget([
    'dataProvider' => $dataProvider,
    'filterModel' => $searchModel,
    'columns' => [
        'id',
        'name',
        'slug',
        'sku',
        'price',
        'stock_qty',
        'is_active:boolean',
        [
            'attribute' => 'category_slug',
            'label' => 'Slug категории',
            'value' => static function ($model) {
                return implode(', ', array_map(static fn($c) => $c->slug, $model->categories));
            },
        ],
        [
            'class' => 'yii\grid\ActionColumn',
            'template' => '{view} {update} {delete}',
        ],
    ],
]) ?>
