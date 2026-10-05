<?php

/** @var yii\web\View $this */
/** @var array<string, mixed> $formData */
?>
<div class="admin-page-block-section">
    <?= $this->render('_block_field', [
        'name' => 'title',
        'label' => 'Заголовок',
        'value' => $formData['title'] ?? '',
    ]) ?>
    <?= $this->render('_block_field', [
        'name' => 'text',
        'label' => 'Текст',
        'type' => 'textarea',
        'rows' => 6,
        'value' => $formData['text'] ?? '',
    ]) ?>
</div>
