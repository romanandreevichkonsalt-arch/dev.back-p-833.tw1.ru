<?php

/** @var yii\web\View $this */
/** @var array<string, mixed> $formData */
?>
<div class="admin-page-block-section">
    <h3 class="admin-page-block-section__title">Поиск в Google (SEO)</h3>
    <div class="admin-content-row__grid admin-content-row__grid--seo">
        <?= $this->render('_block_field', [
            'name' => 'seo_title',
            'label' => 'Title (вкладка и поиск)',
            'value' => $formData['seo_title'] ?? '',
        ]) ?>
        <?= $this->render('_block_field', [
            'name' => 'seo_h1',
            'label' => 'H1 на странице',
            'value' => $formData['seo_h1'] ?? '',
        ]) ?>
    </div>
    <?= $this->render('_block_field', [
        'name' => 'seo_description',
        'label' => 'Meta description',
        'type' => 'textarea',
        'rows' => 3,
        'value' => $formData['seo_description'] ?? '',
        'hint' => 'До ~160 символов после подстановки переменных.',
    ]) ?>
</div>
