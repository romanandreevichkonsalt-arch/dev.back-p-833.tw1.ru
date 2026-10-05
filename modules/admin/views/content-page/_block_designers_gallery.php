<?php

use app\modules\admin\helpers\ContentPageDesignersHelper;

/** @var yii\web\View $this */
/** @var array<string, mixed> $formData */

$photos = ContentPageDesignersHelper::padGalleryPhotosForForm($formData['photos'] ?? []);
?>
<div class="admin-page-block-section">
    <h3 class="admin-page-block-section__title">Текст</h3>
    <p class="admin-muted admin-page-block-section__lead">Центрированный абзац над стопкой фото.</p>
    <?= $this->render('_block_field', [
        'name' => 'gallery_text',
        'label' => 'Текст',
        'type' => 'textarea',
        'rows' => 4,
        'value' => $formData['gallery_text'] ?? '',
    ]) ?>
</div>

<div class="admin-page-block-section" data-repeatable>
    <div class="admin-content-repeatable__toolbar">
        <h3 class="admin-page-block-section__title">Стопка фото</h3>
        <button type="button" class="admin-btn admin-btn--secondary admin-btn--small" data-repeatable-add>Добавить фото</button>
    </div>
    <p class="admin-muted admin-page-block-section__lead">Фото накладываются в стопку. Поворот и смещение — необязательно.</p>
    <div class="admin-designers-gallery-photos" data-repeatable-list>
        <?php foreach ($photos as $i => $row): ?>
            <div class="admin-designers-gallery-photos__item" data-repeatable-item>
                <?= $this->render('_block_row_header', ['title' => 'Фото ' . ($i + 1)]) ?>
                <?= $this->render('@app/modules/admin/views/shared/_media_picker', [
                    'inputName' => "photos[{$i}][image_src]",
                    'altInputName' => "photos[{$i}][image_alt]",
                    'value' => $row['image_src'] ?? '',
                    'altValue' => $row['image_alt'] ?? '',
                    'label' => 'Изображение',
                ]) ?>
                <details class="admin-page-details">
                    <summary>Поворот и смещение</summary>
                    <div class="admin-content-row__grid admin-content-row__grid--triple">
                        <?= $this->render('_block_field', [
                            'name' => "photos[{$i}][rotate]",
                            'label' => 'Поворот (°)',
                            'value' => $row['rotate'] ?? '',
                        ]) ?>
                        <?= $this->render('_block_field', [
                            'name' => "photos[{$i}][offset_x]",
                            'label' => 'Смещение X',
                            'value' => $row['offset_x'] ?? '',
                        ]) ?>
                        <?= $this->render('_block_field', [
                            'name' => "photos[{$i}][offset_y]",
                            'label' => 'Смещение Y',
                            'value' => $row['offset_y'] ?? '',
                        ]) ?>
                    </div>
                </details>
            </div>
        <?php endforeach; ?>
    </div>
    <template data-repeatable-template>
        <div class="admin-designers-gallery-photos__item" data-repeatable-item>
            <?= $this->render('_block_row_header', ['title' => 'Новое фото']) ?>
            <?= $this->render('@app/modules/admin/views/shared/_media_picker', [
                'inputName' => 'photos[__INDEX__][image_src]',
                'altInputName' => 'photos[__INDEX__][image_alt]',
                'value' => '',
                'altValue' => '',
                'label' => 'Изображение',
            ]) ?>
            <details class="admin-page-details">
                <summary>Поворот и смещение</summary>
                <div class="admin-content-row__grid admin-content-row__grid--triple">
                    <?= $this->render('_block_field', ['name' => 'photos[__INDEX__][rotate]', 'label' => 'Поворот (°)', 'value' => '']) ?>
                    <?= $this->render('_block_field', ['name' => 'photos[__INDEX__][offset_x]', 'label' => 'Смещение X', 'value' => '']) ?>
                    <?= $this->render('_block_field', ['name' => 'photos[__INDEX__][offset_y]', 'label' => 'Смещение Y', 'value' => '']) ?>
                </div>
            </details>
        </div>
    </template>
</div>
