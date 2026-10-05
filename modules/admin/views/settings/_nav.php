<?php

use yii\helpers\Html;
use yii\helpers\Url;

/** @var yii\web\View $this */
/** @var string $active */

$items = [
    'direction' => ['label' => 'Направления', 'url' => ['/admin/settings-direction/index']],
    'collection' => ['label' => 'Коллекции', 'url' => ['/admin/settings-collection/index']],
    'category' => ['label' => 'Категории', 'url' => ['/admin/settings-category/index']],
    'color' => ['label' => 'Цвета', 'url' => ['/admin/settings-color/index']],
    'badge' => ['label' => 'Бейджи', 'url' => ['/admin/settings-badge/index']],
];
?>
<nav class="admin-settings-nav">
    <?php foreach ($items as $key => $item): ?>
        <a
            class="admin-settings-nav__link<?= ($active ?? '') === $key ? ' is-active' : '' ?>"
            href="<?= Url::to($item['url']) ?>"
        ><?= Html::encode($item['label']) ?></a>
    <?php endforeach; ?>
</nav>
