<?php

use app\models\Order;
use app\modules\admin\helpers\AdminHtml;
use app\services\order\OrderItemPricingSnapshot;
use app\services\order\OrderLinePricingHelper;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var Order $model */

$this->title = 'Заказ ' . $model->number;

$orderItems = $model->items;
$retailSubtotal = OrderLinePricingHelper::retailSubtotal($orderItems);
$dealerDiscountTotal = OrderLinePricingHelper::dealerDiscountTotal($orderItems);
$dealerPersonalTotal = OrderLinePricingHelper::dealerPersonalDiscountTotal($orderItems);
$catalogPromotionTotal = OrderLinePricingHelper::catalogPromotionDiscountTotal($orderItems);

$formatMoney = static fn (float $amount): string => number_format($amount, 0, '.', ' ') . ' ₽';
?>
<div class="admin-toolbar">
    <div class="admin-actions">
        <?= Html::a('К списку', ['index'], ['class' => 'admin-btn admin-btn--secondary']) ?>
        <?= AdminHtml::actionIcon(['update', 'id' => $model->id], 'update') ?>
    </div>
</div>

<div class="admin-card" style="margin-bottom:16px;">
    <div class="admin-detail-grid">
        <div><span class="admin-detail-label">Номер</span><div><?= Html::encode($model->number) ?></div></div>
        <div><span class="admin-detail-label">Статус</span><div><span class="admin-badge admin-badge--<?= Html::encode($model->status) ?>"><?= Html::encode($model->getStatusLabel()) ?></span></div></div>
        <div><span class="admin-detail-label">Клиент</span><div><?= Html::encode($model->customer_name) ?></div></div>
        <div><span class="admin-detail-label">Телефон</span><div><?= Html::encode($model->customer_phone) ?></div></div>
        <div><span class="admin-detail-label">Email</span><div><?= Html::encode($model->customer_email ?: '—') ?></div></div>
        <div><span class="admin-detail-label">Итого к оплате</span><div><?= Html::encode($model->getFormattedTotal()) ?></div></div>
        <?php if ($model->payment_method): ?>
            <div><span class="admin-detail-label">Оплата</span><div><?= Html::encode(\app\services\order\OrderPaymentMapper::paymentLabel($model->payment_method) ?? $model->payment_method) ?></div></div>
        <?php endif; ?>
        <?php if ($retailSubtotal > 0): ?>
            <div><span class="admin-detail-label">РРЦ (розница)</span><div><?= Html::encode($formatMoney($retailSubtotal)) ?></div></div>
        <?php endif; ?>
        <?php if ($dealerPersonalTotal > 0): ?>
            <div><span class="admin-detail-label">Скидка дилера</span><div>−<?= Html::encode($formatMoney($dealerPersonalTotal)) ?></div></div>
        <?php endif; ?>
        <?php if ($catalogPromotionTotal > 0): ?>
            <div><span class="admin-detail-label">Акции каталога</span><div>−<?= Html::encode($formatMoney($catalogPromotionTotal)) ?></div></div>
        <?php endif; ?>
        <?php if ((float)$model->subtotal_amount > 0): ?>
            <div><span class="admin-detail-label">Сумма после цен дилера</span><div><?= Html::encode($formatMoney((float)$model->subtotal_amount)) ?></div></div>
        <?php endif; ?>
        <?php if ((float)$model->cashless_surcharge_amount > 0): ?>
            <div><span class="admin-detail-label">Наценка безнал</span><div>+<?= Html::encode($formatMoney((float)$model->cashless_surcharge_amount)) ?></div></div>
        <?php endif; ?>
        <?php if ((float)$model->promo_discount_amount > 0 || $model->promoGrant !== null): ?>
            <div>
                <span class="admin-detail-label">Промокод</span>
                <div>
                    <?php if ($model->promoGrant !== null): ?>
                        <?= Html::encode($model->promoGrant->code) ?>
                        <span style="color:#6b6862;">(<?= Html::encode($model->promoGrant->getTitle()) ?>)</span>
                        <?php if ($model->promoGrant->used_at !== null): ?>
                            <span class="admin-badge admin-badge--confirmed" style="margin-left:8px;">Использован</span>
                        <?php endif; ?>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </div>
            </div>
            <div><span class="admin-detail-label">Скидка по промокоду</span><div>−<?= number_format((float)$model->promo_discount_amount, 0, '.', ' ') ?> ₽</div></div>
        <?php endif; ?>
        <?php if ((float)$model->cashback_used_amount > 0): ?>
            <div><span class="admin-detail-label">Списано кэшбека</span><div>−<?= number_format((float)$model->cashback_used_amount, 0, '.', ' ') ?> ₽</div></div>
        <?php endif; ?>
        <div><span class="admin-detail-label">Создан</span><div><?= Html::encode($model->created_at) ?></div></div>
        <?php if ($model->session_id): ?>
            <div><span class="admin-detail-label">Гостевая сессия</span><div><code><?= Html::encode($model->session_id) ?></code></div></div>
        <?php endif; ?>
        <?php if ($model->delivery_address): ?>
            <div><span class="admin-detail-label">Адрес</span><div><?= Html::encode($model->delivery_address) ?></div></div>
        <?php endif; ?>
        <?php if ($model->assignee): ?>
            <div><span class="admin-detail-label">Ответственный</span><div><?= Html::encode($model->assignee->name) ?></div></div>
        <?php endif; ?>
    </div>

    <?php if ($model->comment): ?>
        <div style="margin-top:24px;">
            <span class="admin-detail-label">Комментарий клиента</span>
            <p style="margin:8px 0 0;white-space:pre-wrap;"><?= Html::encode($model->comment) ?></p>
        </div>
    <?php endif; ?>

    <?php if ($model->attachment_path): ?>
        <div style="margin-top:24px;">
            <span class="admin-detail-label">Файл заказа</span>
            <p style="margin:8px 0 0;">
                <?= Html::a(
                    Html::encode($model->attachment_original_name ?: 'Скачать файл'),
                    ['download-attachment', 'id' => $model->id],
                    ['class' => 'admin-btn admin-btn--secondary']
                ) ?>
            </p>
        </div>
    <?php endif; ?>
</div>

<div class="admin-card" style="margin-bottom:16px;">
    <h2 style="margin:0 0 16px;font-size:18px;font-weight:500;">Документы</h2>
    <?php if ($model->documents === []): ?>
        <p style="margin:0 0 16px;color:#6b6862;">Документы не загружены.</p>
    <?php else: ?>
        <table class="admin-table" style="margin-bottom:16px;">
            <thead>
            <tr>
                <th>Название</th>
                <th>Файл</th>
                <th>Дата</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($model->documents as $document): ?>
                <tr>
                    <td><?= Html::encode($document->label) ?></td>
                    <td>
                        <?= Html::a(
                            Html::encode($document->original_name ?: 'Скачать'),
                            ['download-document', 'id' => $model->id, 'documentId' => $document->id],
                            ['class' => 'admin-link']
                        ) ?>
                    </td>
                    <td><?= Html::encode($document->created_at) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <?= Html::beginForm(['upload-document', 'id' => $model->id], 'post', ['enctype' => 'multipart/form-data', 'class' => 'admin-form']) ?>
    <div class="admin-form-row">
        <label class="admin-detail-label" for="order-document-label">Название</label>
        <input id="order-document-label" class="admin-input" type="text" name="label" placeholder="Счёт, ОПД, бланк…" required>
    </div>
    <div class="admin-form-row" style="margin-top:12px;">
        <label class="admin-detail-label" for="order-document-file">Файл</label>
        <input id="order-document-file" class="admin-input" type="file" name="document" required>
    </div>
    <div class="admin-actions" style="margin-top:16px;">
        <?= Html::submitButton('Загрузить документ', ['class' => 'admin-btn admin-btn--secondary']) ?>
    </div>
    <?= Html::endForm() ?>
</div>

<div class="admin-card" style="margin-bottom:16px;">
    <h2 style="margin:0 0 16px;font-size:18px;font-weight:500;">Позиции</h2>
    <table class="admin-table">
        <thead>
        <tr>
            <th>Товар</th>
            <th>Артикул</th>
            <th>Кол-во</th>
            <th>РРЦ</th>
            <th>РРЦ × кол-во</th>
            <th>Цена дилера / акция</th>
            <th>Скидка с РРЦ</th>
            <th>Сумма строки</th>
            <th>Промокод / кэшбек</th>
            <th>К оплате</th>
            <th>Комментарий</th>
            <th>Файл</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($model->items as $item): ?>
            <?php
            $pricing = OrderItemPricingSnapshot::toOrderLineApiFields($item);
            $retailUnit = OrderLinePricingHelper::retailUnitPrice($item);
            $retailLine = OrderLinePricingHelper::retailLineTotal($item);
            $promotion = is_array($pricing['catalogPromotion'] ?? null) ? $pricing['catalogPromotion'] : null;
            $discountLabel = '—';
            if (!empty($pricing['hasCatalogPromotion']) && $promotion !== null) {
                $title = trim((string)($promotion['title'] ?? $promotion['badgeText'] ?? 'Акция'));
                $discountLabel = 'Акция: ' . $title;
            } elseif (isset($pricing['dealerDiscountPercent'])) {
                $discountLabel = 'Скидка дилера ' . (int)$pricing['dealerDiscountPercent'] . '%';
            }
            $checkoutDiscount = (float)$item->promo_discount_amount + (float)$item->cashback_used_amount;
            ?>
            <tr>
                <td><?= Html::encode($item->product_title) ?></td>
                <td><?= Html::encode($item->product_sku ?: '—') ?></td>
                <td><?= (int)$item->quantity ?></td>
                <td><?= Html::encode($formatMoney($retailUnit)) ?></td>
                <td><?= Html::encode($formatMoney($retailLine)) ?></td>
                <td>
                    <?= Html::encode($discountLabel) ?>
                    <div style="color:#6b6862;font-size:12px;">
                        <?= Html::encode($formatMoney((float)$item->unit_price)) ?> / ед.
                    </div>
                </td>
                <td>
                    <?php
                    $dealerSide = (float)($pricing['dealerDiscountAmount'] ?? 0);
                    echo $dealerSide > 0 ? '−' . Html::encode($formatMoney($dealerSide)) : '—';
                    ?>
                </td>
                <td><?= Html::encode($formatMoney((float)$item->line_total)) ?></td>
                <td>
                    <?php
                    if ($checkoutDiscount <= 0) {
                        echo '—';
                    } else {
                        $parts = [];
                        if ((float)$item->promo_discount_amount > 0) {
                            $parts[] = 'промокод −' . $formatMoney((float)$item->promo_discount_amount);
                        }
                        if ((float)$item->cashback_used_amount > 0) {
                            $parts[] = 'кэшбек −' . $formatMoney((float)$item->cashback_used_amount);
                        }
                        echo Html::encode(implode('; ', $parts));
                    }
                    ?>
                </td>
                <td><?= Html::encode($formatMoney((float)$item->paid_line_total)) ?></td>
                <td><?= $item->comment ? Html::encode($item->comment) : '—' ?></td>
                <td>
                    <?php if ($item->attachment_path): ?>
                        <?= Html::a(
                            Html::encode($item->attachment_original_name ?: 'Скачать'),
                            ['download-item-attachment', 'id' => $model->id, 'itemId' => $item->id],
                            ['class' => 'admin-link']
                        ) ?>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="admin-card">
    <h2 style="margin:0 0 16px;font-size:18px;font-weight:500;">История статусов</h2>
    <?php if ($model->statusLogs === []): ?>
        <p style="margin:0;color:#6b6862;">История пуста.</p>
    <?php else: ?>
        <table class="admin-table">
            <thead>
            <tr>
                <th>Дата</th>
                <th>Было</th>
                <th>Стало</th>
                <th>Менеджер</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($model->statusLogs as $log): ?>
                <tr>
                    <td><?= Html::encode($log->created_at) ?></td>
                    <td><?= Html::encode($log->getOldStatusLabel()) ?></td>
                    <td><?= Html::encode($log->getNewStatusLabel()) ?></td>
                    <td><?= Html::encode($log->adminUser->name ?? '—') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
