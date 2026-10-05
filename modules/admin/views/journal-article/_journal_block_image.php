<?php

/** @var yii\web\View $this */
/** @var int|string $bi */
/** @var array<string, mixed> $block */

?>
<?= $this->render('@app/modules/admin/views/shared/_media_picker', [
    'inputName' => "blocks[{$bi}][image_src]",
    'altInputName' => "blocks[{$bi}][image_alt]",
    'value' => $block['image_src'] ?? '',
    'altValue' => $block['image_alt'] ?? '',
    'label' => 'Фото',
]) ?>
<?= $this->render('@app/modules/admin/views/content-page/_block_field', [
    'name' => "blocks[{$bi}][caption]",
    'label' => 'Подпись под фото',
    'type' => 'textarea',
    'rows' => 2,
    'value' => $block['caption'] ?? '',
]) ?>
