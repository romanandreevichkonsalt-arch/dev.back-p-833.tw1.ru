<?php

/** @var yii\web\View $this */
/** @var array<string, mixed> $formData */
/** @var bool $hideImage */

$hideImage = $hideImage ?? false;
?>
<div class="admin-page-block-section">
    <h3 class="admin-page-block-section__title">Текст блока</h3>
    <?= $this->render('_block_field', [
        'name' => 'contact_title',
        'label' => 'Заголовок',
        'value' => $formData['contact_title'] ?? '',
    ]) ?>
    <?= $this->render('_block_field', [
        'name' => 'contact_subtitle',
        'label' => 'Подзаголовок',
        'value' => $formData['contact_subtitle'] ?? '',
    ]) ?>
</div>

<?php if (!$hideImage): ?>
<div class="admin-page-block-section">
    <h3 class="admin-page-block-section__title">Фото рядом с формой</h3>
    <?= $this->render('@app/modules/admin/views/shared/_media_picker', [
        'inputName' => 'image_src',
        'altInputName' => 'image_alt',
        'value' => $formData['image_src'] ?? '',
        'altValue' => $formData['image_alt'] ?? '',
        'label' => 'Изображение',
    ]) ?>
</div>
<?php endif; ?>
