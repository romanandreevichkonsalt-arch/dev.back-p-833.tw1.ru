<?php

use app\models\DealerActivityLog;
use app\models\DealerCredentialsLog;
use app\models\User;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var User $model */
/** @var DealerActivityLog[] $activityLogs */
/** @var DealerCredentialsLog[] $credentialsLogs */

$this->title = $model->getDisplayName();
$profile = $model->dealerProfile;
?>
<div class="admin-toolbar">
    <?= Html::a('← Дилеры', ['index', 'tab' => 'dealers'], ['class' => 'admin-link']) ?>
</div>

<div class="admin-card">
    <div class="admin-detail-grid">
        <div><span class="admin-detail-label">ID</span><div><?= (int)$model->id ?></div></div>
        <div><span class="admin-detail-label">Логин</span><div><?= Html::encode($model->username) ?></div></div>
        <div><span class="admin-detail-label">Компания / ФИО</span><div><?= Html::encode($profile?->company_name ?? '—') ?></div></div>
        <div><span class="admin-detail-label">ИНН</span><div><?= Html::encode($profile?->inn ?? '—') ?></div></div>
        <div><span class="admin-detail-label">Менеджер</span><div><?= Html::encode($profile?->manager_name ?? '—') ?></div></div>
        <div><span class="admin-detail-label">Email</span><div><?= Html::encode($profile?->email ?? '—') ?></div></div>
        <div><span class="admin-detail-label">Телефон</span><div><?= Html::encode($model->getFormattedPhone()) ?></div></div>
        <div><span class="admin-detail-label">Тип</span><div><?= Html::encode($profile?->getTypeLabel() ?? '—') ?></div></div>
        <div><span class="admin-detail-label">Статус</span><div><?= $model->is_blocked ? 'Заблокирован' : 'Активен' ?></div></div>
        <div><span class="admin-detail-label">Профиль заполнен</span><div><?= $model->isProfileComplete() ? 'Да' : 'Нет' ?></div></div>
        <div><span class="admin-detail-label">Доступ отправлен</span><div><?= Html::encode($profile?->credentials_sent_at ?? '—') ?></div></div>
        <div><span class="admin-detail-label">Первый вход</span><div><?= Html::encode($profile?->first_login_at ?? '—') ?></div></div>
        <div><span class="admin-detail-label">Создан</span><div><?= Html::encode($model->created_at) ?></div></div>
    </div>
</div>

<?php if ($credentialsLogs !== []): ?>
<div class="admin-card" style="margin-top:16px;">
    <h3 class="admin-card__title">Отправка доступов</h3>
    <table class="admin-table">
        <thead>
            <tr>
                <th>Дата</th>
                <th>Email</th>
                <th>Результат</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($credentialsLogs as $log): ?>
                <tr>
                    <td><?= Html::encode($log->created_at) ?></td>
                    <td><?= Html::encode($log->email) ?></td>
                    <td><?= $log->is_success ? 'Отправлено' : Html::encode($log->error_message ?? 'Ошибка') ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php if ($activityLogs !== []): ?>
<div class="admin-card" style="margin-top:16px;">
    <h3 class="admin-card__title">История действий</h3>
    <table class="admin-table">
        <thead>
            <tr>
                <th>Дата</th>
                <th>Действие</th>
                <th>IP</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($activityLogs as $log): ?>
                <tr>
                    <td><?= Html::encode($log->created_at) ?></td>
                    <td><?= Html::encode($log->getActionLabel()) ?></td>
                    <td><?= Html::encode($log->ip ?? '—') ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
