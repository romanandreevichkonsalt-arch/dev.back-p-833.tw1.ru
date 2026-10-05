<?php

/** @var yii\web\View $this */
/** @var int|string $bi */
/** @var array<string, mixed> $block */

?>
<?= $this->render('_journal_block_rich_text_field', [
    'name' => "blocks[{$bi}][text]",
    'label' => 'Текст',
    'value' => $block['text'] ?? '',
    'rows' => 3,
]) ?>
