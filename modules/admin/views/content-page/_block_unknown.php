<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var app\models\ContentBlock $block */
?>
<div class="admin-page-block-section">
    <p class="admin-muted">
        Для блока «<?= Html::encode($block->block_key) ?>» не настроена визуальная форма.
        Запустите <code>php yii migrate</code> и при необходимости <code>php yii seed/pages --force</code>.
    </p>
</div>
