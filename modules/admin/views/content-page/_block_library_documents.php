<?php

/** @var yii\web\View $this */
/** @var array<string, mixed> $formData */

$items = $formData['items'] ?? [];
if ($items === []) {
    $items = [['title' => '', 'subtitle' => '', 'file_url' => '']];
}
?>
<div class="admin-page-block-section" data-repeatable>
    <div class="admin-content-repeatable__toolbar">
        <h3 class="admin-page-block-section__title">Документы</h3>
        <button type="button" class="admin-btn admin-btn--secondary admin-btn--small" data-repeatable-add>Добавить документ</button>
    </div>
    <p class="admin-muted admin-page-block-section__lead">
        Карточки в ряд: название, подзаголовок и PDF для скачивания. Размер и дата на сайте не показываются.
    </p>
    <div class="admin-library-documents-grid" data-repeatable-list>
        <?php foreach ($items as $i => $row): ?>
            <div class="admin-content-row admin-library-documents-grid__item" data-repeatable-item>
                <?= $this->render('_block_row_header', ['title' => 'Документ ' . ($i + 1)]) ?>
                <?= $this->render('_block_field', [
                    'name' => "items[{$i}][title]",
                    'label' => 'Название',
                    'value' => $row['title'] ?? '',
                    'hint' => 'Заголовок карточки',
                ]) ?>
                <?= $this->render('_block_field', [
                    'name' => "items[{$i}][subtitle]",
                    'label' => 'Подзаголовок',
                    'type' => 'textarea',
                    'rows' => 3,
                    'value' => $row['subtitle'] ?? '',
                ]) ?>
                <?= $this->render('@app/modules/admin/views/shared/_document_picker', [
                    'inputName' => "items[{$i}][file_url]",
                    'value' => $row['file_url'] ?? '',
                    'label' => 'Файл (PDF)',
                ]) ?>
            </div>
        <?php endforeach; ?>
    </div>
    <template data-repeatable-template>
        <div class="admin-content-row admin-library-documents-grid__item" data-repeatable-item>
            <?= $this->render('_block_row_header', ['title' => 'Новый документ']) ?>
            <?= $this->render('_block_field', [
                'name' => 'items[__INDEX__][title]',
                'label' => 'Название',
                'value' => '',
            ]) ?>
            <?= $this->render('_block_field', [
                'name' => 'items[__INDEX__][subtitle]',
                'label' => 'Подзаголовок',
                'type' => 'textarea',
                'rows' => 3,
                'value' => '',
            ]) ?>
            <?= $this->render('@app/modules/admin/views/shared/_document_picker', [
                'inputName' => 'items[__INDEX__][file_url]',
                'value' => '',
                'label' => 'Файл (PDF)',
            ]) ?>
        </div>
    </template>
</div>
