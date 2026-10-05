<?php

/** @var yii\web\View $this */
/** @var int|string $bi */
/** @var array<string, mixed> $block */

$images = $block['images'] ?? [];
if ($images === []) {
    $images = [['image_src' => '', 'image_alt' => '']];
}
$columns = (int)($block['columns'] ?? 2);
?>
<?= $this->render('@app/modules/admin/views/content-page/_block_field', [
    'name' => "blocks[{$bi}][columns]",
    'label' => 'Колонки',
    'value' => $columns,
    'hint' => '2 или 3',
]) ?>

<div class="admin-content-nested" data-repeatable data-parent-index="<?= htmlspecialchars((string)$bi, ENT_QUOTES) ?>">
    <div class="admin-content-repeatable__toolbar">
        <h4 class="admin-content-nested__title">Фото в галерее</h4>
        <button type="button" class="admin-btn admin-btn--ghost admin-btn--small" data-repeatable-add>Добавить фото</button>
    </div>
    <div data-repeatable-list>
        <?php foreach ($images as $gi => $image): ?>
            <div class="admin-content-row" data-repeatable-item>
                <?= $this->render('@app/modules/admin/views/content-page/_block_row_header', ['title' => 'Фото ' . ($gi + 1)]) ?>
                <?= $this->render('@app/modules/admin/views/shared/_media_picker', [
                    'inputName' => "blocks[{$bi}][images][{$gi}][image_src]",
                    'altInputName' => "blocks[{$bi}][images][{$gi}][image_alt]",
                    'value' => $image['image_src'] ?? '',
                    'altValue' => $image['image_alt'] ?? '',
                    'label' => 'Фото',
                ]) ?>
            </div>
        <?php endforeach; ?>
    </div>
    <template data-repeatable-template>
        <div class="admin-content-row" data-repeatable-item>
            <?= $this->render('@app/modules/admin/views/content-page/_block_row_header', ['title' => 'Новое фото']) ?>
            <?= $this->render('@app/modules/admin/views/shared/_media_picker', [
                'inputName' => 'blocks[__PARENT_INDEX__][images][__INDEX__][image_src]',
                'altInputName' => 'blocks[__PARENT_INDEX__][images][__INDEX__][image_alt]',
                'value' => '',
                'altValue' => '',
                'label' => 'Фото',
            ]) ?>
        </div>
    </template>
</div>
