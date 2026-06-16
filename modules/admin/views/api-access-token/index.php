<?php

/** @var yii\web\View $this */
/** @var yii\data\ActiveDataProvider $dataProvider */

use yii\grid\ActionColumn;
use yii\grid\GridView;
use yii\bootstrap5\Html;

$this->title = 'API-токены';
?>
<h1><?= Html::encode($this->title) ?></h1>
<?= GridView::widget([
    'dataProvider' => $dataProvider,
    'columns' => [
        'id', 'user_id', 'expires_at', 'revoked_at', 'created_at',
        [
            'class' => ActionColumn::class,
            'template' => '{revoke}',
            'buttons' => [
                'revoke' => static fn($url, $model) => $model->revoked_at ? '' : Html::a('Отозвать', ['revoke', 'id' => $model->id], [
                    'class' => 'btn btn-sm btn-outline-danger',
                    'data' => ['method' => 'post', 'confirm' => 'Отозвать токен?'],
                ]),
            ],
        ],
    ],
]) ?>
