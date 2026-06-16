<?php

/** @var yii\web\View $this */
/** @var yii\data\ActiveDataProvider $dataProvider */

use yii\grid\GridView;
use yii\bootstrap5\Html;

$this->title = 'Yandex ID';
?>
<h1><?= Html::encode($this->title) ?></h1>
<?= GridView::widget([
    'dataProvider' => $dataProvider,
    'columns' => [
        'id', 'user_id', 'provider', 'provider_user_id', 'email',
        ['class' => 'yii\grid\ActionColumn', 'template' => '{view}'],
    ],
]) ?>
