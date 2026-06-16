<?php

/** @var yii\web\View $this */
/** @var app\modules\admin\models\ProductForm $form */
/** @var app\models\Product $product */

$this->title = 'Редактировать: ' . $form->name;
?>
<h1><?= yii\bootstrap5\Html::encode($this->title) ?></h1>
<?= $this->render('_form', ['form' => $form, 'product' => $product]) ?>
