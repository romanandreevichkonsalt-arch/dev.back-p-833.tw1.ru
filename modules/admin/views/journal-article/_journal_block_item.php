<?php

use app\modules\admin\helpers\AdminHtml;
use app\services\journal\JournalArticleBlockBuilder;

/** @var yii\web\View $this */
/** @var int|string $bi */
/** @var array<string, mixed> $block */

$type = (string)($block['type'] ?? JournalArticleBlockBuilder::TYPE_TEXT);
$typeLabels = JournalArticleBlockBuilder::typeLabels();
$typeLabel = $typeLabels[$type] ?? $type;
$blockTitle = is_numeric($bi) ? 'Блок ' . ((int)$bi + 1) . ' · ' . $typeLabel : 'Новый блок · ' . $typeLabel;
?>
<div
    class="admin-journal-block admin-journal-block--<?= htmlspecialchars($type, ENT_QUOTES) ?>"
    data-repeatable-item
    data-journal-block
    data-block-type="<?= htmlspecialchars($type, ENT_QUOTES) ?>"
    draggable="true"
>
    <div class="admin-content-row__head admin-journal-block__head">
        <h4 class="admin-content-row__title" data-journal-block-title><?= htmlspecialchars($blockTitle, ENT_QUOTES) ?></h4>
        <div class="admin-journal-block__actions">
            <button type="button" class="admin-icon-btn admin-journal-block__move" data-journal-block-move-up title="Выше" aria-label="Выше">↑</button>
            <button type="button" class="admin-icon-btn admin-journal-block__move" data-journal-block-move-down title="Ниже" aria-label="Ниже">↓</button>
            <?= AdminHtml::repeatableRemoveButton() ?>
        </div>
    </div>

    <input type="hidden" name="blocks[<?= $bi ?>][type]" value="<?= htmlspecialchars($type, ENT_QUOTES) ?>">

    <?php
    $partial = match ($type) {
        JournalArticleBlockBuilder::TYPE_HEADING => '_journal_block_heading',
        JournalArticleBlockBuilder::TYPE_IMAGE => '_journal_block_image',
        JournalArticleBlockBuilder::TYPE_QUOTE => '_journal_block_quote',
        JournalArticleBlockBuilder::TYPE_DIVIDER => '_journal_block_divider',
        JournalArticleBlockBuilder::TYPE_GALLERY => '_journal_block_gallery',
        default => '_journal_block_text',
    };
    echo $this->render($partial, ['bi' => $bi, 'block' => $block]);
    ?>
</div>
