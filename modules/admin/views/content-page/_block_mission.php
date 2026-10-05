<?php

/** @var yii\web\View $this */
/** @var array<string, mixed> $formData */
/** @var app\models\ContentBlock|null $block */
/** @var bool $hideImage */

$hideImage = $hideImage ?? false;
$isPartnersMission = $block !== null
    && $block->page !== null
    && $block->page->slug === 'partners';

$items = $formData['items'] ?? [];
if ($items === []) {
    $items = [['title' => '', 'text' => '', 'image_src' => '', 'image_alt' => '']];
}
?>
<div class="admin-page-block-section" data-repeatable>
    <div class="admin-content-repeatable__toolbar">
        <h3 class="admin-page-block-section__title"><?= $isPartnersMission ? 'Преимущества' : 'Карточки миссии' ?></h3>
        <button type="button" class="admin-btn admin-btn--secondary admin-btn--small" data-repeatable-add>Добавить карточку</button>
    </div>
    <div data-repeatable-list>
        <?php foreach ($items as $i => $row): ?>
            <div class="admin-content-row" data-repeatable-item>
                <?= $this->render('_block_row_header', ['title' => 'Карточка ' . ($i + 1)]) ?>
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
                    'label' => 'Иконка / фото',
                ]) ?>
            </div>
        <?php endforeach; ?>
    </div>
    <template data-repeatable-template>
        <div class="admin-content-row" data-repeatable-item>
            <?= $this->render('_block_row_header', ['title' => 'Новая карточка']) ?>
            <?= $this->render('_block_field', ['name' => 'items[__INDEX__][title]', 'label' => 'Заголовок', 'value' => '']) ?>
            <?= $this->render('_block_field', ['name' => 'items[__INDEX__][text]', 'label' => 'Текст', 'type' => 'textarea', 'rows' => 3, 'value' => '']) ?>
            <?= $this->render('@app/modules/admin/views/shared/_media_picker', [
                'inputName' => 'items[__INDEX__][image_src]',
                'altInputName' => 'items[__INDEX__][image_alt]',
                'value' => '',
                'altValue' => '',
                'label' => 'Иконка / фото',
            ]) ?>
        </div>
    </template>
</div>
