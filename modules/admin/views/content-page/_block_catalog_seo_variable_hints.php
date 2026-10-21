<?php

/** @var yii\web\View $this */
/** @var array<string, string> $variableHints */

use yii\helpers\Html;
?>
<div class="admin-page-block-section admin-catalog-seo-vars">
    <h3 class="admin-page-block-section__title">Переменные в SEO-текстах</h3>
    <p class="admin-muted">Подставляются автоматически при открытии раздела на сайте. Можно использовать в title, description и H1.</p>
    <ul class="admin-catalog-seo-vars__list">
        <?php foreach ($variableHints as $token => $hint): ?>
            <li><code><?= Html::encode($token) ?></code> — <?= Html::encode($hint) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
