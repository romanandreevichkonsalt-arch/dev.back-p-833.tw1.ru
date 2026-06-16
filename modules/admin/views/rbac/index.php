<?php

/** @var yii\web\View $this */
/** @var yii\data\ActiveDataProvider $dataProvider */
/** @var array<string,string> $roles */

use yii\grid\GridView;
use yii\bootstrap5\Html;

$this->title = 'Роли пользователей';
?>
<h1><?= Html::encode($this->title) ?></h1>
<?= GridView::widget([
    'dataProvider' => $dataProvider,
    'columns' => [
        'id', 'username', 'phone',
        [
            'label' => 'Роли',
            'value' => static fn($model) => implode(', ', $model->getRoleNames()),
        ],
        [
            'class' => 'yii\grid\ActionColumn',
            'template' => '{assign}',
            'buttons' => [
                'assign' => static fn($url, $model) => Html::a('Роли', ['assign', 'id' => $model->id], ['class' => 'btn btn-sm btn-primary']),
            ],
        ],
    ],
]) ?>
