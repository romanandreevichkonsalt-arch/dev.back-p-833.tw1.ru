<?php

/** @var yii\web\View $this */
/** @var app\models\AttributeDefinition $model */

$this->title = 'Создать атрибут';
?>
<h1><?= yii\bootstrap5\Html::encode($this->title) ?></h1>
<?= $this->render('_form', ['model' => $model]) ?>
