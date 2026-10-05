<?php

/** @var yii\web\View $this */
/** @var array<string, mixed> $formData */

$items = $formData['items'] ?? [];
if ($items === []) {
    $items = [['author' => '', 'title' => '', 'project_name' => '', 'image_src' => '', 'image_alt' => '']];
}
?>
<div class="admin-page-block-section">
    <h3 class="admin-page-block-section__title">Заголовок блока</h3>
    <?= $this->render('_block_field', [
        'name' => 'title',
        'label' => 'Заголовок',
        'value' => $formData['title'] ?? '',
        'hint' => 'Например: «Реализованные идеи»',
    ]) ?>
    <?= $this->render('_block_field', [
        'name' => 'description',
        'label' => 'Описание',
        'type' => 'textarea',
        'rows' => 4,
        'value' => $formData['description'] ?? '',
    ]) ?>
</div>

<div class="admin-page-block-section" data-repeatable>
    <div class="admin-content-repeatable__toolbar">
        <h3 class="admin-page-block-section__title">Карточки проектов</h3>
        <button type="button" class="admin-btn admin-btn--secondary admin-btn--small" data-repeatable-add>Добавить карточку</button>
    </div>
    <p class="admin-muted admin-page-block-section__lead">
        На сайте: автор вверху слева, название изделия и проект внизу на фото.
    </p>
    <div data-repeatable-list>
        <?php foreach ($items as $i => $row): ?>
            <div class="admin-content-row" data-repeatable-item>
                <?= $this->render('_block_row_header', ['title' => 'Карточка ' . ($i + 1)]) ?>
                <?= $this->render('_block_field', [
                    'name' => "items[{$i}][author]",
                    'label' => 'Автор проекта',
                    'value' => $row['author'] ?? '',
                    'hint' => 'Имя без префикса «Автор проекта:»',
                ]) ?>
                <?= $this->render('_block_field', [
                    'name' => "items[{$i}][title]",
                    'label' => 'Изделие',
                    'value' => $row['title'] ?? '',
                    'hint' => 'Например: «Кресло 3» или «Диван 3 и кресло 1»',
                ]) ?>
                <?= $this->render('_block_field', [
                    'name' => "items[{$i}][project_name]",
                    'label' => 'Название проекта',
                    'value' => $row['project_name'] ?? '',
                    'hint' => 'На сайте: «В проекте …»',
                ]) ?>
                <?= $this->render('@app/modules/admin/views/shared/_media_picker', [
                    'inputName' => "items[{$i}][image_src]",
                    'altInputName' => "items[{$i}][image_alt]",
                    'value' => $row['image_src'] ?? '',
                    'altValue' => $row['image_alt'] ?? '',
                    'label' => 'Фото интерьера',
                ]) ?>
            </div>
        <?php endforeach; ?>
    </div>
    <template data-repeatable-template>
        <div class="admin-content-row" data-repeatable-item>
            <?= $this->render('_block_row_header', ['title' => 'Новая карточка']) ?>
            <?= $this->render('_block_field', [
                'name' => 'items[__INDEX__][author]',
                'label' => 'Автор проекта',
                'value' => '',
            ]) ?>
            <?= $this->render('_block_field', [
                'name' => 'items[__INDEX__][title]',
                'label' => 'Изделие',
                'value' => '',
            ]) ?>
            <?= $this->render('_block_field', [
                'name' => 'items[__INDEX__][project_name]',
                'label' => 'Название проекта',
                'value' => '',
            ]) ?>
            <?= $this->render('@app/modules/admin/views/shared/_media_picker', [
                'inputName' => 'items[__INDEX__][image_src]',
                'altInputName' => 'items[__INDEX__][image_alt]',
                'value' => '',
                'altValue' => '',
                'label' => 'Фото интерьера',
            ]) ?>
        </div>
    </template>
</div>
