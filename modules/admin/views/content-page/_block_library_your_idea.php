<?php

/** @var yii\web\View $this */
/** @var array<string, mixed> $formData */
?>
<div class="admin-page-block-section">
    <h3 class="admin-page-block-section__title">Фото и текст</h3>
    <p class="admin-muted admin-page-block-section__lead">Слева на сайте — вертикальное фото, справа — заголовок, абзацы и ссылка.</p>
    <div class="admin-page-media-grid">
        <div class="admin-page-media-grid__item">
            <?= $this->render('@app/modules/admin/views/shared/_media_picker', [
                'inputName' => 'image_src',
                'altInputName' => 'image_alt',
                'value' => $formData['image_src'] ?? '',
                'altValue' => $formData['image_alt'] ?? '',
                'label' => 'Фото слева',
            ]) ?>
        </div>
        <div class="admin-page-media-grid__item">
            <?= $this->render('_block_field', [
                'name' => 'title',
                'label' => 'Заголовок',
                'value' => $formData['title'] ?? '',
                'hint' => 'Например: «Ваша идея — наше исполнение»',
            ]) ?>
            <?= $this->render('_block_field', [
                'name' => 'text',
                'label' => 'Текст (первый абзац)',
                'type' => 'textarea',
                'rows' => 5,
                'value' => $formData['text'] ?? '',
            ]) ?>
            <?= $this->render('_block_field', [
                'name' => 'text_secondary',
                'label' => 'Текст (второй абзац)',
                'type' => 'textarea',
                'rows' => 4,
                'value' => $formData['text_secondary'] ?? '',
            ]) ?>
            <?= $this->render('_block_field', [
                'name' => 'cta_label',
                'label' => 'Текст ссылки',
                'value' => $formData['cta_label'] ?? '',
                'hint' => 'Например: «Получить предложение»',
            ]) ?>
            <?= $this->render('_block_field', [
                'name' => 'cta_href',
                'label' => 'Адрес ссылки',
                'value' => $formData['cta_href'] ?? '',
                'hint' => 'Например: /designers или /contacts',
            ]) ?>
        </div>
    </div>
</div>
