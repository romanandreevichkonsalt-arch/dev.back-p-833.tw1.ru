<?php

/** @var yii\web\View $this */
/** @var yii\data\ActiveDataProvider $dataProvider */

use yii\grid\GridView;
use yii\bootstrap5\Html;

$this->title = 'Пользователи';
?>
<div class="d-flex justify-content-between mb-3">
    <h1 class="mb-0"><?= Html::encode($this->title) ?></h1>
    <?= Html::a('Создать', ['create'], ['class' => 'btn btn-primary']) ?>
</div>
<?= GridView::widget([
    'dataProvider' => $dataProvider,
    'columns' => [
        'id', 'phone', 'username', 'created_at',
        [
            'label' => 'Роли',
            'value' => static function ($model) {
                return implode(', ', $model->getRoleNames());
            },
        ],
        ['class' => 'yii\grid\ActionColumn', 'template' => '{view} {update}'],
    ],
]) ?>
