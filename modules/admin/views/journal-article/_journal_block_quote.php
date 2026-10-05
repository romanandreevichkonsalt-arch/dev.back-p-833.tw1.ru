<?php

/** @var yii\web\View $this */
/** @var int|string $bi */
/** @var array<string, mixed> $block */

?>
<?= $this->render('_journal_block_rich_text_field', [
    'name' => "blocks[{$bi}][text]",
    'label' => 'Цитата',
    'value' => $block['text'] ?? '',
    'rows' => 3,
]) ?>
<?= $this->render('@app/modules/admin/views/content-page/_block_field', [
    'name' => "blocks[{$bi}][author]",
    'label' => 'Атрибуция',
    'value' => $block['author'] ?? '',
    'hint' => 'Например: ведущий дизайнер бренда',
]) ?>
