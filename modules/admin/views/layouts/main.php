<?php

use app\modules\admin\assets\AdminAsset;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var string $content */

AdminAsset::register($this);
?>
<?php $this->beginPage() ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="<?= Yii::$app->charset ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?= Html::csrfMetaTags() ?>
    <title><?= Html::encode($this->title ? $this->title . ' — Админка МФ Анна' : 'Админка МФ Анна') ?></title>
    <?php $this->head() ?>
</head>
<body class="admin-body">
<?php $this->beginBody() ?>

<div class="admin-shell">
    <?= $this->render('_sidebar') ?>
    <div class="admin-main">
        <?= $this->render('_topbar') ?>
        <main class="admin-content">
            <?= $this->render('_flash') ?>
            <?= $content ?>
        </main>
    </div>
</div>

<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage() ?>
