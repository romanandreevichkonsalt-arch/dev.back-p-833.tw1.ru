<?php

/** @var yii\web\View $this */
/** @var array<string, mixed> $formData */
?>
<div class="admin-page-block-section">
    <h3 class="admin-page-block-section__title">Поиск в Google (SEO)</h3>
    <div class="admin-content-row__grid admin-content-row__grid--seo">
        <?= $this->render('_block_field', [
            'name' => 'seo_title',
            'label' => 'Заголовок страницы',
            'value' => $formData['seo_title'] ?? '',
            'hint' => 'Отображается во вкладке браузера и в результатах поиска.',
        ]) ?>
        <?= $this->render('_block_field', [
            'name' => 'seo_description',
            'label' => 'Описание страницы',
            'type' => 'textarea',
            'rows' => 3,
            'value' => $formData['seo_description'] ?? '',
            'hint' => 'Короткий текст для поисковиков, до ~160 символов.',
        ]) ?>
    </div>
</div>
