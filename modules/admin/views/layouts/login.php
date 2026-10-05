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
    <title><?= Html::encode($this->title ? $this->title . ' — Вход' : 'Вход в админку') ?></title>
    <?php $this->head() ?>
</head>
<body class="admin-body">
<?php $this->beginBody() ?>

<div class="admin-login">
    <div class="admin-login__card admin-card">
        <?= $content ?>
    </div>
</div>

<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage() ?>
