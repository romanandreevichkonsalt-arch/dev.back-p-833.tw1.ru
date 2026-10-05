<?php

/** @var yii\web\View $this */
/** @var array<string, mixed> $formData */
?>
<div class="admin-page-block-section">
    <h3 class="admin-page-block-section__title">Контактные данные</h3>
    <?= $this->render('_block_field', [
        'name' => 'info_phone',
        'label' => 'Телефон (как показать)',
        'value' => $formData['info_phone'] ?? '',
        'hint' => 'Например: +7 (495) 000-00-00',
    ]) ?>
    <?= $this->render('_block_field', [
        'name' => 'info_phone_href',
        'label' => 'Телефон для ссылки',
        'value' => $formData['info_phone_href'] ?? '',
        'hint' => 'Например: +74950000000 — без пробелов, для клика «позвонить».',
    ]) ?>
    <?= $this->render('_block_field', [
        'name' => 'info_email',
        'label' => 'Email',
        'value' => $formData['info_email'] ?? '',
    ]) ?>
    <?= $this->render('_block_field', [
        'name' => 'info_address',
        'label' => 'Адрес',
        'value' => $formData['info_address'] ?? '',
    ]) ?>
    <?= $this->render('_block_field', [
        'name' => 'info_telegram',
        'label' => 'Telegram',
        'value' => $formData['info_telegram'] ?? '',
        'hint' => 'Ссылка на канал, чат или профиль, например https://t.me/…',
    ]) ?>
    <?= $this->render('_block_field', [
        'name' => 'info_vkontakte',
        'label' => 'ВКонтакте',
        'value' => $formData['info_vkontakte'] ?? '',
        'hint' => 'Ссылка на сообщество или профиль, например https://vk.com/…',
    ]) ?>
    <?= $this->render('_block_field', [
        'name' => 'info_max',
        'label' => 'MAX',
        'value' => $formData['info_max'] ?? '',
        'hint' => 'Ссылка на профиль или чат в мессенджере MAX.',
    ]) ?>
</div>
