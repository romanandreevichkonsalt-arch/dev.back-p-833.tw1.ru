<?php

use yii\helpers\Html;

/** @var yii\web\View $this */

$user = Yii::$app->adminUser->identity;
?>
<header class="admin-topbar">
    <h1 class="admin-topbar__title"><?= Html::encode($this->title ?: 'Админка') ?></h1>
    <?php if ($user !== null): ?>
        <div class="admin-topbar__user">
            <?= Html::encode($user->name) ?> · <?= Html::encode($user->getRoleLabel()) ?>
        </div>
    <?php endif; ?>
</header>
