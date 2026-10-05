<?php

/** @var yii\web\View $this */
/** @var array<string, mixed> $formData */

$items = $formData['items'] ?? [];
if ($items === []) {
    $items = [['title' => '', 'text' => '']];
}
?>
<div class="admin-page-block-section" data-repeatable>
    <div class="admin-content-repeatable__toolbar">
        <h3 class="admin-page-block-section__title">Условия</h3>
        <button type="button" class="admin-btn admin-btn--secondary admin-btn--small" data-repeatable-add>Добавить пункт</button>
    </div>
    <div data-repeatable-list>
        <?php foreach ($items as $i => $row): ?>
            <div class="admin-content-row admin-content-row--compact" data-repeatable-item>
                <?= $this->render('_block_row_header', ['title' => 'Пункт ' . ($i + 1)]) ?>
                <?= $this->render('_block_field', [
                    'name' => "items[{$i}][title]",
                    'label' => 'Заголовок',
                    'value' => $row['title'] ?? '',
                ]) ?>
                <?= $this->render('_block_field', [
                    'name' => "items[{$i}][text]",
                    'label' => 'Текст',
                    'type' => 'textarea',
                    'rows' => 3,
                    'value' => $row['text'] ?? '',
                ]) ?>
            </div>
        <?php endforeach; ?>
    </div>
    <template data-repeatable-template>
        <div class="admin-content-row admin-content-row--compact" data-repeatable-item>
            <?= $this->render('_block_row_header', ['title' => 'Новый пункт']) ?>
            <?= $this->render('_block_field', ['name' => 'items[__INDEX__][title]', 'label' => 'Заголовок', 'value' => '']) ?>
            <?= $this->render('_block_field', ['name' => 'items[__INDEX__][text]', 'label' => 'Текст', 'type' => 'textarea', 'rows' => 3, 'value' => '']) ?>
        </div>
    </template>
</div>
