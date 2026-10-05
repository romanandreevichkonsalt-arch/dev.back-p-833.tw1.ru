<?php

/** @var yii\web\View $this */
/** @var int|string $bi */
/** @var array<string, mixed> $block */

$level = (int)($block['level'] ?? 2);
if ($level < 2 || $level > 3) {
    $level = 2;
}
?>
<div class="admin-content-row__grid">
    <?= $this->render('@app/modules/admin/views/content-page/_block_field', [
        'name' => "blocks[{$bi}][level]",
        'label' => 'Уровень',
        'value' => $level,
        'hint' => '2 — подзаголовок секции, 3 — меньший заголовок',
    ]) ?>
</div>
<?= $this->render('@app/modules/admin/views/content-page/_block_field', [
    'name' => "blocks[{$bi}][text]",
    'label' => 'Заголовок',
    'value' => $block['text'] ?? '',
]) ?>
