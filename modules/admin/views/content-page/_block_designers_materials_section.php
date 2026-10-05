<?php

use app\modules\admin\helpers\ContentPageDesignersHelper;

/** @var yii\web\View $this */
/** @var array<string, mixed> $formData */

$items = ContentPageDesignersHelper::padMaterialsItemsForForm($formData['items'] ?? []);
?>
<div class="admin-page-block-section">
    <h3 class="admin-page-block-section__title">Фон и текст</h3>
    <p class="admin-muted admin-page-block-section__lead">Фото на фоне, вводный текст и ссылка на архив.</p>
    <?= $this->render('@app/modules/admin/views/shared/_media_picker', [
        'inputName' => 'materials_image_src',
        'altInputName' => 'materials_image_alt',
        'value' => $formData['materials_image_src'] ?? '',
        'altValue' => $formData['materials_image_alt'] ?? '',
        'label' => 'Фото на фоне',
    ]) ?>
    <?= $this->render('_block_field', [
        'name' => 'materials_text',
        'label' => 'Текст над колонками',
        'type' => 'textarea',
        'rows' => 3,
        'value' => $formData['materials_text'] ?? '',
    ]) ?>
    <div class="admin-designers-materials-archive">
        <?= $this->render('_block_field', [
            'name' => 'archive_label',
            'label' => 'Текст ссылки',
            'value' => $formData['archive_label'] ?? '',
            'inputOptions' => ['placeholder' => 'Скачать полный архив'],
        ]) ?>
        <?= $this->render('@app/modules/admin/views/shared/_document_picker', [
            'inputName' => 'archive_url',
            'value' => $formData['archive_url'] ?? '',
            'label' => 'Файл архива',
        ]) ?>
    </div>
</div>

<div class="admin-page-block-section">
    <h3 class="admin-page-block-section__title">Три колонки</h3>
    <p class="admin-muted admin-page-block-section__lead">Заголовок и описание в каждой колонке внизу блока.</p>
    <div class="admin-designers-materials-columns">
        <?php foreach ($items as $i => $row): ?>
            <div class="admin-designers-materials-columns__item">
                <?= $this->render('_block_row_header', [
                    'title' => 'Колонка ' . ($i + 1),
                    'removable' => false,
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
                    'rows' => 4,
                    'value' => $row['text'] ?? '',
                ]) ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>
