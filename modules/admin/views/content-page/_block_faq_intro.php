<?php

/** @var yii\web\View $this */
/** @var array<string, mixed> $formData */
?>
<div class="admin-page-block-section">
    <h3 class="admin-page-block-section__title">Текст под баннером</h3>
    <?= $this->render('_block_field', [
        'name' => 'intro_lead',
        'label' => 'Заголовок',
        'value' => $formData['intro_lead'] ?? '',
        'hint' => 'На макете: «FAQs».',
    ]) ?>
    <?= $this->render('_block_field', [
        'name' => 'intro_text',
        'label' => 'Подзаголовок',
        'type' => 'textarea',
        'rows' => 3,
        'value' => $formData['intro_text'] ?? '',
    ]) ?>
</div>
