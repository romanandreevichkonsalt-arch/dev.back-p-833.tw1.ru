<?php

/** @var yii\web\View $this */
/** @var app\models\Product $model */

use yii\bootstrap5\Html;
use yii\widgets\DetailView;

$this->title = $model->name;
?>
<h1><?= Html::encode($this->title) ?></h1>
<p>
    <?= Html::a('Редактировать', ['update', 'id' => $model->id], ['class' => 'btn btn-primary']) ?>
    <?= Html::a('Удалить', ['delete', 'id' => $model->id], [
        'class' => 'btn btn-danger',
        'data' => ['method' => 'post', 'confirm' => 'Удалить товар?'],
    ]) ?>
</p>
<?= DetailView::widget([
    'model' => $model,
    'attributes' => [
        'id', 'name', 'slug', 'sku', 'description:ntext', 'price', 'old_price', 'stock_qty', 'is_active:boolean',
        'seo_title', 'seo_description', 'seo_h1', 'created_at', 'updated_at',
        [
            'label' => 'Категории',
            'format' => 'raw',
            'value' => implode('<br>', array_map(
                static fn($c) => \yii\bootstrap5\Html::encode($c->getDisplayLabel()),
                $model->categories
            )),
        ],
    ],
]) ?>
