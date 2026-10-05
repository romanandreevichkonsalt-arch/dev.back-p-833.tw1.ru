<?php

use app\modules\admin\helpers\PromoCodeConditions;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var list<string> $lines */
/** @var string $title */

$title = $title ?? 'Условия применения';
?>
<?php if ($lines !== []): ?>
<div class="admin-card admin-promo-conditions" style="margin-top:16px;">
    <h3 class="admin-card__title"><?= Html::encode($title) ?></h3>
    <ul class="admin-promo-conditions__list">
        <?php foreach ($lines as $line): ?>
            <li><?= Html::encode($line) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>
