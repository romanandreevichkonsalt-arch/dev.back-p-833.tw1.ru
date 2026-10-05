<?php

use yii\helpers\Html;

/** @var array{username:string,password:string,cabinetUrl?:string} $data */

$cabinetUrl = trim((string)($data['cabinetUrl'] ?? ''));
$lines = [
    'Личный кабинет дилера — МФ Анна',
    'Логин: ' . $data['username'],
    'Пароль: ' . $data['password'],
];
if ($cabinetUrl !== '') {
    $lines[] = 'Ссылка: ' . $cabinetUrl;
}
$copyText = implode("\n", $lines);
?>
<div class="admin-flash admin-flash--success admin-dealer-access-flash">
    <div class="admin-dealer-access-flash__text">
        <strong>Доступ для дилера</strong><br>
        Логин: <code><?= Html::encode($data['username']) ?></code><br>
        Пароль: <code><?= Html::encode($data['password']) ?></code>
        <?php if ($cabinetUrl !== ''): ?>
            <br>Ссылка: <?= Html::a(Html::encode($cabinetUrl), $cabinetUrl, ['target' => '_blank', 'rel' => 'noopener']) ?>
        <?php endif; ?>
    </div>
    <button
        type="button"
        class="admin-btn admin-btn--secondary"
        data-copy-dealer-access="<?= Html::encode($copyText) ?>"
    >Скопировать доступ</button>
</div>
