<?php

/** @var yii\web\View $this */
/** @var app\models\AttributeDefinition $model */

use yii\bootstrap5\Html;
use yii\widgets\DetailView;

$this->title = $model->name;
?>
<h1><?= Html::encode($this->title) ?></h1>
<?= DetailView::widget(['model' => $model, 'attributes' => ['id', 'code', 'name', 'type', 'is_filterable:boolean', 'is_required:boolean', 'created_at', 'updated_at']]) ?>
