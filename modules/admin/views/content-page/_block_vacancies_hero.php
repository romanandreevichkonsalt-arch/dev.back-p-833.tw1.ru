<?php

/** @var yii\web\View $this */
/** @var array<string, mixed> $formData */
/** @var bool $withSeo */

$withSeo = $withSeo ?? false;
?>
<?php if ($withSeo): ?>
<div class="admin-page-block-section">
    <h3 class="admin-page-block-section__title">Поиск в Google (SEO)</h3>
    <div class="admin-content-row__grid admin-content-row__grid--seo">
        <?= $this->render('_block_field', [
            'name' => 'seo_title',
            'label' => 'Заголовок страницы',
            'value' => $formData['seo_title'] ?? '',
            'hint' => 'Вкладка браузера и результаты поиска.',
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
<?php endif; ?>

<div class="admin-page-block-section">
    <h3 class="admin-page-block-section__title">Фото баннера</h3>
    <div class="admin-page-media-grid">
        <div class="admin-page-media-grid__item">
            <?= $this->render('@app/modules/admin/views/shared/_media_picker', [
                'inputName' => 'image_desktop_src',
                'altInputName' => 'image_desktop_alt',
                'value' => $formData['image_desktop_src'] ?? '',
                'altValue' => $formData['image_desktop_alt'] ?? '',
                'label' => 'Фото для компьютера',
            ]) ?>
        </div>
        <div class="admin-page-media-grid__item">
            <?= $this->render('@app/modules/admin/views/shared/_media_picker', [
                'inputName' => 'image_mobile_src',
                'altInputName' => 'image_mobile_alt',
                'value' => $formData['image_mobile_src'] ?? '',
                'altValue' => $formData['image_mobile_alt'] ?? '',
                'label' => 'Фото для телефона',
            ]) ?>
        </div>
    </div>
</div>

<div class="admin-page-block-section">
    <h3 class="admin-page-block-section__title">Тексты на баннере</h3>
    <p class="admin-muted admin-page-block-section__lead">Подпись, заголовок и подзаголовок слева внизу. Кнопку на баннере не выводим.</p>
    <?= $this->render('_block_field', [
        'name' => 'collection_label',
        'label' => 'Подпись',
        'value' => $formData['collection_label'] ?? '',
        'hint' => 'Например: «Вакансии»',
    ]) ?>
    <?= $this->render('_block_field', [
        'name' => 'collection_title',
        'label' => 'Заголовок',
        'value' => $formData['collection_title'] ?? '',
        'hint' => 'Например: «Культура труда и созидания»',
    ]) ?>
    <?= $this->render('_block_field', [
        'name' => 'tagline',
        'label' => 'Подзаголовок',
        'type' => 'textarea',
        'rows' => 3,
        'value' => $formData['tagline'] ?? '',
        'hint' => 'Текст под заголовком.',
    ]) ?>
</div>
