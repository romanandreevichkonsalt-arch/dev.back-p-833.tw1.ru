<?php

/** @var yii\web\View $this */
/** @var array<string, mixed> $formData */

$items = $formData['items'] ?? [];
if ($items === []) {
    $items = [['number' => '', 'title' => '', 'text' => '', 'image_src' => '', 'image_alt' => '']];
}
?>
<div class="admin-page-block-section">
    <h3 class="admin-page-block-section__title">Этика и стандарты</h3>
    <?= $this->render('_block_field', [
        'name' => 'stack_intro_title',
        'label' => 'Заголовок',
        'value' => $formData['stack_intro_title'] ?? '',
    ]) ?>
    <?= $this->render('_block_field', [
        'name' => 'stack_intro_text',
        'label' => 'Текст',
        'type' => 'textarea',
        'rows' => 6,
        'value' => $formData['stack_intro_text'] ?? '',
    ]) ?>
</div>

<div class="admin-page-block-section" data-repeatable>
    <div class="admin-content-repeatable__toolbar">
        <h3 class="admin-page-block-section__title">Блоки</h3>
        <button type="button" class="admin-btn admin-btn--secondary admin-btn--small" data-repeatable-add>Добавить блок</button>
    </div>
    <div data-repeatable-list>
        <?php foreach ($items as $i => $row): ?>
            <div class="admin-content-row" data-repeatable-item>
                <?= $this->render('_block_row_header', ['title' => 'Блок ' . ($i + 1)]) ?>
                <div class="admin-page-media-grid admin-stack-item-grid">
                    <div class="admin-page-media-grid__item">
                        <div class="admin-content-row__grid">
                            <?= $this->render('_block_field', [
                                'name' => "items[{$i}][number]",
                                'label' => 'Номер',
                                'value' => $row['number'] ?? '',
                            ]) ?>
                            <?= $this->render('_block_field', [
                                'name' => "items[{$i}][title]",
                                'label' => 'Заголовок',
                                'value' => $row['title'] ?? '',
                            ]) ?>
                        </div>
                        <?= $this->render('_block_field', [
                            'name' => "items[{$i}][text]",
                            'label' => 'Текст',
                            'type' => 'textarea',
                            'rows' => 3,
                            'value' => $row['text'] ?? '',
                        ]) ?>
                    </div>
                    <div class="admin-page-media-grid__item">
                        <?= $this->render('@app/modules/admin/views/shared/_media_picker', [
                            'inputName' => "items[{$i}][image_src]",
                            'altInputName' => "items[{$i}][image_alt]",
                            'value' => $row['image_src'] ?? '',
                            'altValue' => $row['image_alt'] ?? '',
                            'label' => 'Фото',
                        ]) ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <template data-repeatable-template>
        <div class="admin-content-row" data-repeatable-item>
            <?= $this->render('_block_row_header', ['title' => 'Новый блок']) ?>
            <div class="admin-page-media-grid admin-stack-item-grid">
                <div class="admin-page-media-grid__item">
                    <div class="admin-content-row__grid">
                        <?= $this->render('_block_field', ['name' => 'items[__INDEX__][number]', 'label' => 'Номер', 'value' => '']) ?>
                        <?= $this->render('_block_field', ['name' => 'items[__INDEX__][title]', 'label' => 'Заголовок', 'value' => '']) ?>
                    </div>
                    <?= $this->render('_block_field', ['name' => 'items[__INDEX__][text]', 'label' => 'Текст', 'type' => 'textarea', 'rows' => 3, 'value' => '']) ?>
                </div>
                <div class="admin-page-media-grid__item">
                    <?= $this->render('@app/modules/admin/views/shared/_media_picker', [
                        'inputName' => 'items[__INDEX__][image_src]',
                        'altInputName' => 'items[__INDEX__][image_alt]',
                        'value' => '',
                        'altValue' => '',
                        'label' => 'Фото',
                    ]) ?>
                </div>
            </div>
        </div>
    </template>
</div>
