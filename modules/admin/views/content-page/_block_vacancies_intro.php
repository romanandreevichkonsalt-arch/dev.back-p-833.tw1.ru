<?php

/** @var yii\web\View $this */
/** @var array<string, mixed> $formData */

$paragraphs = $formData['paragraphs'] ?? [];
if ($paragraphs === []) {
    $paragraphs = [['text' => '']];
}

$slides = $formData['slides'] ?? [];
if ($slides === []) {
    $slides = [['image_src' => '', 'image_alt' => '']];
}
?>
<div class="admin-page-block-section">
    <?= $this->render('_block_field', [
        'name' => 'values_title',
        'label' => 'Заголовок',
        'value' => $formData['values_title'] ?? '',
    ]) ?>
</div>

<div class="admin-page-block-section">
    <h3 class="admin-page-block-section__title">Главное изображение</h3>
    <?= $this->render('@app/modules/admin/views/shared/_media_picker', [
        'inputName' => 'intro_image_src',
        'altInputName' => 'intro_image_alt',
        'value' => $formData['intro_image_src'] ?? '',
        'altValue' => $formData['intro_image_alt'] ?? '',
        'label' => 'Фото блока',
    ]) ?>
</div>

<div class="admin-page-block-section" data-repeatable>
    <div class="admin-content-repeatable__toolbar">
        <h3 class="admin-page-block-section__title">Галерея</h3>
        <button type="button" class="admin-btn admin-btn--secondary admin-btn--small" data-repeatable-add>Добавить фото</button>
    </div>
    <p class="admin-muted admin-page-block-section__lead">Сетка фото, как ракурсы модели в каталоге.</p>
    <div class="admin-gallery-photos-grid" data-repeatable-list>
        <?php foreach ($slides as $i => $row): ?>
            <div class="admin-gallery-photos-grid__item" data-repeatable-item>
                <?= $this->render('_block_row_header', ['title' => 'Фото ' . ($i + 1)]) ?>
                <?= $this->render('@app/modules/admin/views/shared/_media_picker', [
                    'inputName' => "slides[{$i}][image_src]",
                    'altInputName' => "slides[{$i}][image_alt]",
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
                'inputName' => 'slides[__INDEX__][image_src]',
                'altInputName' => 'slides[__INDEX__][image_alt]',
                'value' => '',
                'altValue' => '',
                'label' => 'Изображение',
            ]) ?>
        </div>
    </template>
</div>

<div class="admin-page-block-section" data-repeatable>
    <div class="admin-content-repeatable__toolbar">
        <h3 class="admin-page-block-section__title">Текст</h3>
        <button type="button" class="admin-btn admin-btn--secondary admin-btn--small" data-repeatable-add>Добавить абзац</button>
    </div>
    <p class="admin-muted admin-page-block-section__lead">Каждый абзац — отдельный параграф на странице.</p>
    <div data-repeatable-list>
        <?php foreach ($paragraphs as $i => $row): ?>
            <div class="admin-content-row admin-content-row--compact" data-repeatable-item>
                <?= $this->render('_block_row_header', ['title' => 'Абзац ' . ($i + 1)]) ?>
                <?= $this->render('_block_field', [
                    'name' => "paragraphs[{$i}][text]",
                    'label' => 'Текст абзаца',
                    'type' => 'textarea',
                    'rows' => 4,
                    'value' => $row['text'] ?? '',
                ]) ?>
            </div>
        <?php endforeach; ?>
    </div>
    <template data-repeatable-template>
        <div class="admin-content-row admin-content-row--compact" data-repeatable-item>
            <?= $this->render('_block_row_header', ['title' => 'Новый абзац']) ?>
            <?= $this->render('_block_field', [
                'name' => 'paragraphs[__INDEX__][text]',
                'label' => 'Текст абзаца',
                'type' => 'textarea',
                'rows' => 4,
                'value' => '',
            ]) ?>
        </div>
    </template>
</div>
