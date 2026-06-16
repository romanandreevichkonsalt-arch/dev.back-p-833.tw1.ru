<?php

/** @var yii\web\View $this */
/** @var app\models\ExternalIdentity $model */

use yii\widgets\DetailView;
use yii\bootstrap5\Html;

$this->title = 'External identity #' . $model->id;
?>
<h1><?= Html::encode($this->title) ?></h1>
<?= DetailView::widget([
    'model' => $model,
    'attributes' => ['id', 'user_id', 'provider', 'provider_user_id', 'email', 'raw_payload:ntext'],
]) ?>
