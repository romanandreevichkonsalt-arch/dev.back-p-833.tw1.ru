<?php

/** @var yii\web\View $this */
/** @var array<string, mixed> $formData */
?>
<div class="admin-page-block-section">
    <h3 class="admin-page-block-section__title">Фото и текст формы</h3>
    <p class="admin-muted admin-page-block-section__lead">Фото слева, заголовки формы, файлы политики конфиденциальности и пользовательского соглашения справа.</p>
    <div class="admin-partners-contact-layout__body">
        <div class="admin-partners-contact-layout__photo">
            <?= $this->render('@app/modules/admin/views/shared/_media_picker', [
                'inputName' => 'image_src',
                'altInputName' => 'image_alt',
                'value' => $formData['image_src'] ?? '',
                'altValue' => $formData['image_alt'] ?? '',
                'label' => 'Изображение',
            ]) ?>
        </div>
        <div class="admin-partners-contact-layout__content">
            <?= $this->render('_block_field', [
                'name' => 'contact_title',
                'label' => 'Заголовок',
                'type' => 'textarea',
                'rows' => 2,
                'value' => $formData['contact_title'] ?? '',
            ]) ?>
            <?= $this->render('_block_field', [
                'name' => 'contact_subtitle',
                'label' => 'Подзаголовок',
                'type' => 'textarea',
                'rows' => 2,
                'value' => $formData['contact_subtitle'] ?? '',
            ]) ?>
            <?= $this->render('@app/modules/admin/views/shared/_document_picker', [
                'inputName' => 'privacy_policy_url',
                'value' => $formData['privacy_policy_url'] ?? '',
                'label' => 'Файл политики конфиденциальности',
            ]) ?>
            <?= $this->render('@app/modules/admin/views/shared/_document_picker', [
                'inputName' => 'user_agreement_url',
                'value' => $formData['user_agreement_url'] ?? '',
                'label' => 'Файл пользовательского соглашения',
            ]) ?>
        </div>
    </div>
</div>
