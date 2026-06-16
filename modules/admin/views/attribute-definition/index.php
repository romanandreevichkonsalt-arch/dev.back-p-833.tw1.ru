<?php

/** @var yii\web\View $this */
/** @var yii\data\ActiveDataProvider $dataProvider */

use yii\grid\GridView;
use yii\bootstrap5\Html;

$this->title = 'Атрибуты';
?>
<div class="d-flex justify-content-between mb-3">
    <h1 class="mb-0"><?= Html::encode($this->title) ?></h1>
    <?= Html::a('Создать', ['create'], ['class' => 'btn btn-primary']) ?>
</div>
<?= GridView::widget([
    'dataProvider' => $dataProvider,
    'columns' => ['id', 'code', 'name', 'type', 'is_filterable:boolean', 'is_required:boolean', ['class' => 'yii\grid\ActionColumn']],
]) ?>
