<?php

/** @var yii\web\View $this */
/** @var array<string, mixed> $formData */

$items = $formData['items'] ?? [];
if ($items === []) {
    $items = [['id' => '', 'title' => '', 'text' => '', 'image_src' => '', 'image_alt' => '']];
}
?>
<div class="admin-page-block-section" data-repeatable>
    <div class="admin-content-repeatable__toolbar">
        <h3 class="admin-page-block-section__title">Элементы списка</h3>
        <button type="button" class="admin-btn admin-btn--secondary admin-btn--small" data-repeatable-add>Добавить элемент</button>
    </div>
    <div data-repeatable-list>
        <?php foreach ($items as $i => $row): ?>
            <div class="admin-content-row" data-repeatable-item>
                <?= $this->render('_block_row_header', ['title' => 'Элемент ' . ($i + 1)]) ?>
                <?= $this->render('_block_field', [
                    'name' => "items[{$i}][id]",
                    'label' => 'ID',
                    'value' => $row['id'] ?? '',
                ]) ?>
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
                <?= $this->render('@app/modules/admin/views/shared/_media_picker', [
                    'inputName' => "items[{$i}][image_src]",
                    'altInputName' => "items[{$i}][image_alt]",
                    'value' => $row['image_src'] ?? '',
                    'altValue' => $row['image_alt'] ?? '',
                    'label' => 'Фото',
                ]) ?>
            </div>
        <?php endforeach; ?>
    </div>
    <template data-repeatable-template>
        <div class="admin-content-row" data-repeatable-item>
            <?= $this->render('_block_row_header', ['title' => 'Новый элемент']) ?>
            <?= $this->render('_block_field', ['name' => 'items[__INDEX__][id]', 'label' => 'ID', 'value' => '']) ?>
            <?= $this->render('_block_field', ['name' => 'items[__INDEX__][title]', 'label' => 'Заголовок', 'value' => '']) ?>
            <?= $this->render('_block_field', ['name' => 'items[__INDEX__][text]', 'label' => 'Текст', 'type' => 'textarea', 'rows' => 3, 'value' => '']) ?>
            <?= $this->render('@app/modules/admin/views/shared/_media_picker', [
                'inputName' => 'items[__INDEX__][image_src]',
                'altInputName' => 'items[__INDEX__][image_alt]',
                'value' => '',
                'altValue' => '',
                'label' => 'Фото',
            ]) ?>
        </div>
    </template>
</div>
