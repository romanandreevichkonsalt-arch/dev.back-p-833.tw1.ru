<?php

/** @var yii\web\View $this */
/** @var string $content */

use app\assets\AppAsset;
use app\modules\admin\components\AdminMenu;
use app\widgets\Alert;
use yii\bootstrap5\Html;

AppAsset::register($this);
?>
<?php $this->beginPage() ?>
<!DOCTYPE html>
<html lang="<?= Yii::$app->language ?>">
<head>
    <meta charset="<?= Yii::$app->charset ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?= Html::csrfMetaTags() ?>
    <title><?= Html::encode($this->title) ?> — Админка</title>
    <?php $this->head() ?>
    <style>
        body { min-height: 100vh; }
        .admin-sidebar { min-width: 220px; }
        .admin-content { flex: 1; }
    </style>
</head>
<body>
<?php $this->beginBody() ?>
<div class="d-flex">
    <aside class="admin-sidebar bg-dark text-white p-3 min-vh-100">
        <h5 class="mb-3">Fabrika Admin</h5>
        <?= AdminMenu::widget() ?>
        <hr class="border-secondary">
        <div class="small">
            <?= Html::encode(Yii::$app->user->identity->username ?? '') ?>
            <?= Html::beginForm(['/admin/auth/logout'], 'post', ['class' => 'mt-2']) ?>
            <?= Html::submitButton('Выйти', ['class' => 'btn btn-sm btn-outline-light w-100']) ?>
            <?= Html::endForm() ?>
        </div>
    </aside>
    <main class="admin-content p-4">
        <?= Alert::widget() ?>
        <?= $content ?>
    </main>
</div>
<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage() ?>
