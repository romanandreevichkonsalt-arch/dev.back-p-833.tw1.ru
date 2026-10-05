<?php

/** @var yii\web\View $this */
/** @var array<string, mixed> $formData */
?>
<div class="admin-page-block-section">
    <h3 class="admin-page-block-section__title">Этика производства</h3>
    <?= $this->render('_block_field', [
        'name' => 'title',
        'label' => 'Заголовок',
        'value' => $formData['title'] ?? '',
    ]) ?>
    <?= $this->render('_block_field', [
        'name' => 'text',
        'label' => 'Текст',
        'type' => 'textarea',
        'rows' => 4,
        'value' => $formData['text'] ?? '',
    ]) ?>
    <?= $this->render('@app/modules/admin/views/shared/_media_picker', [
        'inputName' => 'image_src',
        'altInputName' => 'image_alt',
        'value' => $formData['image_src'] ?? '',
        'altValue' => $formData['image_alt'] ?? '',
        'label' => 'Фото',
    ]) ?>
</div>
