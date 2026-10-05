<?php

use app\models\Lead;
use app\models\Order;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var int $newLeadsCount */
/** @var int $openLeadsCount */
/** @var int $newOrdersCount */
/** @var int $openOrdersCount */
/** @var int $totalLeadsCount */
/** @var int $totalOrdersCount */
/** @var Lead[] $recentLeads */
/** @var Order[] $recentOrders */

$this->title = 'Дашборд';

$leadTypeLabels = Lead::typeLabels();
?>
<div class="admin-grid" style="margin-bottom: 24px;">
    <div class="admin-stat">
        <div class="admin-stat__label">Новые заявки сегодня</div>
        <div class="admin-stat__value"><?= (int)$newLeadsCount ?></div>
    </div>
    <div class="admin-stat">
        <div class="admin-stat__label">Открытые заявки</div>
        <div class="admin-stat__value"><?= (int)$openLeadsCount ?></div>
    </div>
    <div class="admin-stat">
        <div class="admin-stat__label">Новые заказы сегодня</div>
        <div class="admin-stat__value"><?= (int)$newOrdersCount ?></div>
    </div>
    <div class="admin-stat">
        <div class="admin-stat__label">Открытые заказы</div>
        <div class="admin-stat__value"><?= (int)$openOrdersCount ?></div>
    </div>
</div>

<div class="admin-grid" style="grid-template-columns: 1fr 1fr; margin-bottom: 24px;">
    <div class="admin-card">
        <div class="admin-toolbar">
            <h2 style="margin:0;font-size:18px;font-weight:500;">Последние заявки</h2>
            <?= Html::a('Все заявки', ['/admin/lead/index'], ['class' => 'admin-link']) ?>
        </div>
        <?php if ($recentLeads === []): ?>
            <p style="color:#6b6862;margin:0;">Заявок пока нет.</p>
        <?php else: ?>
            <table class="admin-table">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Тип</th>
                    <th>Клиент</th>
                    <th>Статус</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($recentLeads as $lead): ?>
                    <tr>
                        <td><?= Html::a('#' . $lead->id, ['/admin/lead/view', 'id' => $lead->id]) ?></td>
                        <td><?= Html::encode($leadTypeLabels[$lead->type] ?? $lead->type) ?></td>
                        <td><?= Html::encode($lead->name) ?></td>
                        <td><span class="admin-badge admin-badge--<?= Html::encode($lead->status) ?>"><?= Html::encode($lead->getStatusLabel()) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="admin-card">
        <div class="admin-toolbar">
            <h2 style="margin:0;font-size:18px;font-weight:500;">Последние заказы</h2>
            <?= Html::a('Все заказы', ['/admin/order/index'], ['class' => 'admin-link']) ?>
        </div>
        <?php if ($recentOrders === []): ?>
            <p style="color:#6b6862;margin:0;">Заказов пока нет.</p>
        <?php else: ?>
            <table class="admin-table">
                <thead>
                <tr>
                    <th>Номер</th>
                    <th>Клиент</th>
                    <th>Сумма</th>
                    <th>Статус</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($recentOrders as $order): ?>
                    <tr>
                        <td><?= Html::a(Html::encode($order->number), ['/admin/order/view', 'id' => $order->id]) ?></td>
                        <td><?= Html::encode($order->customer_name) ?></td>
                        <td><?= Html::encode($order->getFormattedTotal()) ?></td>
                        <td><span class="admin-badge admin-badge--<?= Html::encode($order->status) ?>"><?= Html::encode($order->getStatusLabel()) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<div class="admin-grid">
    <div class="admin-stat">
        <div class="admin-stat__label">Всего заявок</div>
        <div class="admin-stat__value"><?= (int)$totalLeadsCount ?></div>
    </div>
    <div class="admin-stat">
        <div class="admin-stat__label">Всего заказов</div>
        <div class="admin-stat__value"><?= (int)$totalOrdersCount ?></div>
    </div>
</div>
