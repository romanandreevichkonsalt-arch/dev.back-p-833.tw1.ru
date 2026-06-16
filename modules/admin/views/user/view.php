<?php

/** @var yii\web\View $this */
/** @var app\models\User $model */

use yii\bootstrap5\Html;
use yii\widgets\DetailView;

$this->title = $model->username ?: $model->phone;
?>
<h1><?= Html::encode($this->title) ?></h1>
<?= DetailView::widget([
    'model' => $model,
    'attributes' => [
        'id', 'phone', 'username', 'created_at', 'updated_at',
        ['label' => 'Роли', 'value' => implode(', ', $model->getRoleNames())],
        ['label' => 'Профиль', 'value' => $model->profile ? $model->profile->display_name : '—'],
    ],
]) ?>
