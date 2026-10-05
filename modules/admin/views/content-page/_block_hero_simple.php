<?php

/** @var yii\web\View $this */
/** @var array<string, mixed> $formData */
?>
<div class="admin-page-block-section">
    <h3 class="admin-page-block-section__title">Текст баннера</h3>
    <?= $this->render('_block_field', [
        'name' => 'hero_title',
        'label' => 'Заголовок',
        'value' => $formData['hero_title'] ?? '',
    ]) ?>
    <?= $this->render('_block_field', [
        'name' => 'hero_subtitle',
        'label' => 'Подзаголовок',
        'value' => $formData['hero_subtitle'] ?? '',
    ]) ?>
</div>
