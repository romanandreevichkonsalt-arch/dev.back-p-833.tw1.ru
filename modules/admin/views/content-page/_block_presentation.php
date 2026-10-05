<?php

/** @var yii\web\View $this */
/** @var array<string, mixed> $formData */
?>
<div class="admin-page-block-section">
    <h3 class="admin-page-block-section__title">Презентация</h3>
    <?= $this->render('_block_field', [
        'name' => 'title',
        'label' => 'Заголовок блока',
        'value' => $formData['title'] ?? '',
    ]) ?>
    <?= $this->render('_block_field', [
        'name' => 'file_url',
        'label' => 'Ссылка на PDF',
        'value' => $formData['file_url'] ?? '',
        'hint' => 'Полный URL файла презентации.',
    ]) ?>
    <?= $this->render('_block_field', [
        'name' => 'file_label',
        'label' => 'Текст кнопки',
        'value' => $formData['file_label'] ?? '',
    ]) ?>
</div>
