<?php

/** @var yii\web\View $this */
/** @var int|string $stageIndex */
/** @var array<int, array<string, string>> $images */

$images = $images ?? [];
if ($images === []) {
    $images = [['image_src' => '', 'image_alt' => '']];
}
?>
<div class="admin-about-timeline-stage-grid__photos" data-repeatable data-parent-index="<?= htmlspecialchars((string)$stageIndex, ENT_QUOTES) ?>">
    <div class="admin-content-repeatable__toolbar">
        <h4 class="admin-content-nested__title">Фото</h4>
        <button type="button" class="admin-btn admin-btn--ghost admin-btn--small" data-repeatable-add>Добавить фото</button>
    </div>
    <div class="admin-gallery-photos-grid admin-gallery-photos-grid--timeline" data-repeatable-list>
        <?php foreach ($images as $ii => $image): ?>
            <div class="admin-gallery-photos-grid__item" data-repeatable-item>
                <?= $this->render('_block_row_header', ['title' => 'Фото ' . ($ii + 1)]) ?>
                <?= $this->render('@app/modules/admin/views/shared/_media_picker', [
                    'inputName' => "stages[{$stageIndex}][images][{$ii}][image_src]",
                    'altInputName' => "stages[{$stageIndex}][images][{$ii}][image_alt]",
                    'value' => $image['image_src'] ?? '',
                    'altValue' => $image['image_alt'] ?? '',
                    'label' => 'Изображение',
                ]) ?>
            </div>
        <?php endforeach; ?>
    </div>
    <template data-repeatable-template>
        <div class="admin-gallery-photos-grid__item" data-repeatable-item>
            <?= $this->render('_block_row_header', ['title' => 'Новое фото']) ?>
            <?= $this->render('@app/modules/admin/views/shared/_media_picker', [
                'inputName' => 'stages[__PARENT_INDEX__][images][__INDEX__][image_src]',
                'altInputName' => 'stages[__PARENT_INDEX__][images][__INDEX__][image_alt]',
                'value' => '',
                'altValue' => '',
                'label' => 'Изображение',
            ]) ?>
        </div>
    </template>
</div>
