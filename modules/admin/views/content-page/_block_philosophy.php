<?php

/** @var yii\web\View $this */
/** @var array<string, mixed> $formData */
?>
<div class="admin-page-block-section">
    <h3 class="admin-page-block-section__title">Философия бренда</h3>
    <?= $this->render('_block_field', [
        'name' => 'philosophy_text',
        'label' => 'Текст',
        'type' => 'textarea',
        'rows' => 5,
        'value' => $formData['philosophy_text'] ?? '',
        'hint' => 'Короткий абзац о ценностях и подходе бренда.',
    ]) ?>
</div>
