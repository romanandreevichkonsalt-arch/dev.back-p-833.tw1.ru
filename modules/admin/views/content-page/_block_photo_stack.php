<?php

/** @var yii\web\View $this */
/** @var array<string, mixed> $formData */

$items = $formData['items'] ?? [];
if ($items === []) {
    $items = [['image_src' => '', 'image_alt' => '', 'rotate' => '', 'offset_x' => '', 'offset_y' => '']];
}
?>
<div class="admin-page-block-section" data-repeatable>
    <div class="admin-content-repeatable__toolbar">
        <h3 class="admin-page-block-section__title">Фото в стопке</h3>
        <button type="button" class="admin-btn admin-btn--secondary admin-btn--small" data-repeatable-add>Добавить фото</button>
    </div>
    <div data-repeatable-list>
        <?php foreach ($items as $i => $row): ?>
            <div class="admin-content-row" data-repeatable-item>
                <?= $this->render('_block_row_header', ['title' => 'Фото ' . ($i + 1)]) ?>
                <?= $this->render('@app/modules/admin/views/shared/_media_picker', [
                    'inputName' => "items[{$i}][image_src]",
                    'altInputName' => "items[{$i}][image_alt]",
                    'value' => $row['image_src'] ?? '',
                    'altValue' => $row['image_alt'] ?? '',
                    'label' => 'Изображение',
                ]) ?>
                <div class="admin-content-row__grid">
                    <?= $this->render('_block_field', [
                        'name' => "items[{$i}][rotate]",
                        'label' => 'Поворот (°)',
                        'value' => $row['rotate'] ?? '',
                    ]) ?>
                    <?= $this->render('_block_field', [
                        'name' => "items[{$i}][offset_x]",
                        'label' => 'Смещение X',
                        'value' => $row['offset_x'] ?? '',
                    ]) ?>
                    <?= $this->render('_block_field', [
                        'name' => "items[{$i}][offset_y]",
                        'label' => 'Смещение Y',
                        'value' => $row['offset_y'] ?? '',
                    ]) ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <template data-repeatable-template>
        <div class="admin-content-row" data-repeatable-item>
            <?= $this->render('_block_row_header', ['title' => 'Новое фото']) ?>
            <?= $this->render('@app/modules/admin/views/shared/_media_picker', [
                'inputName' => 'items[__INDEX__][image_src]',
                'altInputName' => 'items[__INDEX__][image_alt]',
                'value' => '',
                'altValue' => '',
                'label' => 'Изображение',
            ]) ?>
            <div class="admin-content-row__grid">
                <?= $this->render('_block_field', ['name' => 'items[__INDEX__][rotate]', 'label' => 'Поворот (°)', 'value' => '']) ?>
                <?= $this->render('_block_field', ['name' => 'items[__INDEX__][offset_x]', 'label' => 'Смещение X', 'value' => '']) ?>
                <?= $this->render('_block_field', ['name' => 'items[__INDEX__][offset_y]', 'label' => 'Смещение Y', 'value' => '']) ?>
            </div>
        </div>
    </template>
</div>
