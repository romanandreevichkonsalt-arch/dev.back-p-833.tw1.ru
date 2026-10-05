<?php

use app\models\User;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var User $model */

$this->title = $model->getDisplayName();
$profile = $model->profile;
?>
<div class="admin-toolbar">
    <?= Html::a('← Пользователи', ['index', 'tab' => 'customers'], ['class' => 'admin-link']) ?>
</div>

<div class="admin-card">
    <div class="admin-detail-grid">
        <div><span class="admin-detail-label">ID</span><div><?= (int)$model->id ?></div></div>
        <div><span class="admin-detail-label">Имя</span><div><?= Html::encode($model->getDisplayName()) ?></div></div>
        <div><span class="admin-detail-label">Телефон</span><div><?= Html::encode($model->getFormattedPhone()) ?></div></div>
        <div><span class="admin-detail-label">Email</span><div><?= Html::encode($profile?->email ?? '—') ?></div></div>
        <div><span class="admin-detail-label">Создан</span><div><?= Html::encode($model->created_at) ?></div></div>
    </div>
</div>
