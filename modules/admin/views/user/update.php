<?php

/** @var yii\web\View $this */
/** @var app\models\User $model */
/** @var app\models\UserProfile $profile */

$this->title = 'Редактировать: ' . ($model->username ?: $model->phone);
?>
<h1><?= yii\bootstrap5\Html::encode($this->title) ?></h1>
<?= $this->render('_form', ['model' => $model, 'profile' => $profile]) ?>
