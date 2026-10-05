<?php

use app\models\DealerManager;
use app\models\DealerProfile;
use app\modules\admin\helpers\AdminHtml;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var DealerManager[] $managers */
?>
<div class="admin-card">
    <p class="admin-muted">Контакты менеджеров фабрики для личного кабинета дилера. Учётная запись админки не создаётся.</p>
    <?php if ($managers === []): ?>
        <p class="admin-muted">Менеджеров пока нет.</p>
    <?php else: ?>
        <table class="admin-table">
            <thead>
            <tr>
                <th>ФИО</th>
                <th>Телефон</th>
                <th>Email</th>
                <th>График</th>
                <th>Должность</th>
                <th>Дилеров</th>
                <th>Активен</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($managers as $manager): ?>
                <?php $assignedCount = DealerProfile::find()->where(['assigned_manager_id' => $manager->id])->count(); ?>
                <tr>
                    <td><?= Html::encode($manager->name) ?></td>
                    <td><?= Html::encode($manager->phone) ?></td>
                    <td><?= Html::encode($manager->email) ?></td>
                    <td><?= Html::encode($manager->work_hours) ?></td>
                    <td><?= Html::encode($manager->role_label) ?></td>
                    <td><?= (int)$assignedCount ?></td>
                    <td><?= $manager->is_active ? 'Да' : 'Нет' ?></td>
                    <td class="admin-table-actions">
                        <?= AdminHtml::actionIcon(['/admin/dealer-manager/update', 'id' => $manager->id], 'update') ?>
                        <?= AdminHtml::actionIcon(['/admin/dealer-manager/deactivate', 'id' => $manager->id], 'delete', [
                            'data-method' => 'post',
                            'data-confirm' => 'Деактивировать или удалить менеджера?',
                        ]) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
