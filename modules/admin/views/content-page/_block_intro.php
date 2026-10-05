<?php

/** @var yii\web\View $this */
/** @var array<string, mixed> $formData */
?>
<div class="admin-page-block-section">
    <h3 class="admin-page-block-section__title">Вступление</h3>
    <?= $this->render('_block_field', [
        'name' => 'intro_lead',
        'label' => 'Акцентная строка',
        'value' => $formData['intro_lead'] ?? '',
        'hint' => 'Короткая выделенная фраза.',
    ]) ?>
    <?= $this->render('_block_field', [
        'name' => 'intro_text',
        'label' => 'Основной текст',
        'type' => 'textarea',
        'rows' => 5,
        'value' => $formData['intro_text'] ?? '',
    ]) ?>
</div>
