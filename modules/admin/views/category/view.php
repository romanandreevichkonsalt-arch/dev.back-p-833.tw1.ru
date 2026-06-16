<?php

/** @var yii\web\View $this */
/** @var app\models\Category $model */

use yii\bootstrap5\Html;
use yii\widgets\DetailView;

$this->title = $model->name;
?>
<h1><?= Html::encode($this->title) ?></h1>
<p>
    <?= Html::a('Редактировать', ['update', 'id' => $model->id], ['class' => 'btn btn-primary']) ?>
    <?= Html::a('Удалить', ['delete', 'id' => $model->id], [
        'class' => 'btn btn-danger',
        'data' => ['method' => 'post', 'confirm' => 'Удалить категорию?'],
    ]) ?>
</p>
<?= DetailView::widget([
    'model' => $model,
    'attributes' => ['id', 'parent_id', 'name', 'slug', 'description', 'seo_title', 'seo_description', 'seo_h1', 'is_active:boolean', 'sort_order', 'created_at', 'updated_at'],
]) ?>
