<?php

/** @var yii\web\View $this */
/** @var array<string, mixed> $formData */

$cards = $formData['cards'] ?? [];
if ($cards === []) {
    $cards = [['image_src' => '', 'image_alt' => '']];
}
?>
<div class="admin-page-block-section">
    <?= $this->render('_block_field', [
        'name' => 'gallery_title',
        'label' => 'Заголовок галереи',
        'value' => $formData['gallery_title'] ?? '',
    ]) ?>
</div>

<div class="admin-page-block-section" data-repeatable>
    <div class="admin-content-repeatable__toolbar">
        <h3 class="admin-page-block-section__title">Фото</h3>
        <button type="button" class="admin-btn admin-btn--secondary admin-btn--small" data-repeatable-add>Добавить фото</button>
    </div>
    <div class="admin-gallery-photos-grid" data-repeatable-list>
        <?php foreach ($cards as $i => $row): ?>
            <div class="admin-gallery-photos-grid__item" data-repeatable-item>
                <?= $this->render('_block_row_header', ['title' => 'Фото ' . ($i + 1)]) ?>
                <?= $this->render('@app/modules/admin/views/shared/_media_picker', [
                    'inputName' => "cards[{$i}][image_src]",
                    'altInputName' => "cards[{$i}][image_alt]",
                    'value' => $row['image_src'] ?? '',
                    'altValue' => $row['image_alt'] ?? '',
                    'label' => 'Изображение',
                ]) ?>
            </div>
        <?php endforeach; ?>
    </div>
    <template data-repeatable-template>
        <div class="admin-gallery-photos-grid__item" data-repeatable-item>
            <?= $this->render('_block_row_header', ['title' => 'Новое фото']) ?>
            <?= $this->render('@app/modules/admin/views/shared/_media_picker', [
                'inputName' => 'cards[__INDEX__][image_src]',
                'altInputName' => 'cards[__INDEX__][image_alt]',
                'value' => '',
                'altValue' => '',
                'label' => 'Изображение',
            ]) ?>
        </div>
    </template>
</div>
