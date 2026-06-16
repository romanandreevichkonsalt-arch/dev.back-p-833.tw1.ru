<?php

/** @var yii\web\View $this */
/** @var yii\data\ActiveDataProvider $dataProvider */

use yii\grid\GridView;
use yii\bootstrap5\Html;

$this->title = 'SMS-коды';
?>
<h1><?= Html::encode($this->title) ?></h1>
<?= GridView::widget([
    'dataProvider' => $dataProvider,
    'columns' => [
        'id', 'user_id', 'phone',
        [
            'attribute' => 'code',
            'value' => static fn($model) => YII_ENV_PROD ? '******' : $model->code,
        ],
        'expires_at', 'used_at', 'created_at',
    ],
]) ?>
