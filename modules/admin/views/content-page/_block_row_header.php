<?php

use app\modules\admin\helpers\AdminHtml;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var string $title */
/** @var bool $removable */
/** @var bool $faqItemTitle */

$removable = $removable ?? true;
$faqItemTitle = $faqItemTitle ?? false;
?>
<div class="admin-content-row__head">
    <h4 class="admin-content-row__title"<?= $faqItemTitle ? ' data-faq-item-title' : '' ?>><?= Html::encode($title) ?></h4>
    <?php if ($removable): ?>
        <?= AdminHtml::repeatableRemoveButton() ?>
    <?php endif; ?>
</div>
