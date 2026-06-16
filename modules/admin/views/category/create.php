<?php

/** @var yii\web\View $this */
/** @var app\models\Category $model */

$this->title = 'Создать категорию';
?>
<h1><?= yii\bootstrap5\Html::encode($this->title) ?></h1>
<?= $this->render('_form', ['model' => $model]) ?>
