<?php

/** @var yii\web\View $this */
/** @var array<string, mixed> $formData */
?>
<div class="admin-page-block-section">
    <h3 class="admin-page-block-section__title">Фото баннера</h3>
    <div class="admin-page-media-grid">
        <div class="admin-page-media-grid__item">
            <?= $this->render('@app/modules/admin/views/shared/_media_picker', [
                'inputName' => 'image_desktop_src',
                'altInputName' => 'image_desktop_alt',
                'value' => $formData['image_desktop_src'] ?? '',
                'altValue' => $formData['image_desktop_alt'] ?? '',
                'label' => 'Фото для компьютера',
            ]) ?>
        </div>
        <div class="admin-page-media-grid__item">
            <?= $this->render('@app/modules/admin/views/shared/_media_picker', [
                'inputName' => 'image_mobile_src',
                'altInputName' => 'image_mobile_alt',
                'value' => $formData['image_mobile_src'] ?? '',
                'altValue' => $formData['image_mobile_alt'] ?? '',
                'label' => 'Фото для телефона',
            ]) ?>
        </div>
    </div>
</div>

<div class="admin-page-block-section">
    <h3 class="admin-page-block-section__title">Тексты на баннере</h3>
    <?= $this->render('_block_field', [
        'name' => 'hero_title',
        'label' => 'Заголовок',
        'value' => $formData['hero_title'] ?? '',
    ]) ?>
    <?= $this->render('_block_field', [
        'name' => 'hero_subtitle',
        'label' => 'Подзаголовок',
        'type' => 'textarea',
        'rows' => 2,
        'value' => $formData['hero_subtitle'] ?? '',
    ]) ?>
</div>
