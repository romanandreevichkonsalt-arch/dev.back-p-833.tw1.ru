<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var string $orderNumber */
/** @var string|null $adminOrderUrl */
/** @var string $dealerCompanyName */
/** @var string $dealerInn */
/** @var string $dealerUsername */
/** @var string $customerName */
/** @var string $customerPhone */
/** @var string|null $customerEmail */
/** @var string|null $deliveryAddress */
/** @var string $paymentLabel */
/** @var string|null $retailSubtotalAmount */
/** @var string|null $dealerPersonalDiscountAmount */
/** @var string|null $catalogPromotionDiscountAmount */
/** @var string $subtotalAmount */
/** @var string|null $promoDiscountAmount */
/** @var string|null $promoCode */
/** @var string|null $cashbackUsedAmount */
/** @var string|null $cashlessSurchargeAmount */
/** @var string $totalAmount */
/** @var list<array{title: string, quantity: int, retailLineTotal: string, lineTotal: string, paidLineTotal: string, pricingNote?: string|null, comment?: string, hasAttachment?: bool}> $items */
/** @var string $createdAt */

$this->title = 'Новый заказ дилера';
?>
<p>Дилер оформил новый заказ <strong><?= Html::encode($orderNumber) ?></strong>.</p>

<p><strong>Дилер:</strong><br>
<?= Html::encode($dealerCompanyName !== '' ? $dealerCompanyName : '—') ?><br>
ИНН: <?= Html::encode($dealerInn !== '' ? $dealerInn : '—') ?><br>
Логин: <?= Html::encode($dealerUsername) ?></p>

<p><strong>Клиент заказа:</strong><br>
<?= Html::encode($customerName) ?><br>
<?= Html::encode($customerPhone) ?>
<?php if ($customerEmail !== null && trim($customerEmail) !== ''): ?>
    <br><?= Html::encode($customerEmail) ?>
<?php endif; ?>
<?php if ($deliveryAddress !== null && trim($deliveryAddress) !== ''): ?>
    <br>Адрес: <?= Html::encode($deliveryAddress) ?>
<?php endif; ?>
</p>

<p><strong>Оплата:</strong> <?= Html::encode($paymentLabel) ?><br>
<?php if ($retailSubtotalAmount !== null): ?>
    РРЦ: <?= Html::encode($retailSubtotalAmount) ?> ₽<br>
<?php endif; ?>
<?php if ($dealerPersonalDiscountAmount !== null): ?>
    Скидка дилера: −<?= Html::encode($dealerPersonalDiscountAmount) ?> ₽<br>
<?php endif; ?>
<?php if ($catalogPromotionDiscountAmount !== null): ?>
    Акции каталога: −<?= Html::encode($catalogPromotionDiscountAmount) ?> ₽<br>
<?php endif; ?>
Сумма после цен дилера: <?= Html::encode($subtotalAmount) ?> ₽
<?php if ($promoDiscountAmount !== null): ?>
    <br>Скидка по промокоду<?= $promoCode !== null ? ' (' . Html::encode($promoCode) . ')' : '' ?>: −<?= Html::encode($promoDiscountAmount) ?> ₽
<?php endif; ?>
<?php if ($cashbackUsedAmount !== null): ?>
    <br>Списано кэшбека: −<?= Html::encode($cashbackUsedAmount) ?> ₽
<?php endif; ?>
<?php if ($cashlessSurchargeAmount !== null): ?>
    <br>Наценка безнал: +<?= Html::encode($cashlessSurchargeAmount) ?> ₽
<?php endif; ?>
<br><strong>Итого: <?= Html::encode($totalAmount) ?> ₽</strong></p>

<p><strong>Позиции:</strong></p>
<ol>
<?php foreach ($items as $item): ?>
    <li>
        <?= Html::encode($item['title']) ?> × <?= (int)$item['quantity'] ?>
        — РРЦ <?= Html::encode($item['retailLineTotal']) ?> ₽,
        строка <?= Html::encode($item['lineTotal']) ?> ₽,
        к оплате <?= Html::encode($item['paidLineTotal']) ?> ₽
        <?php if (!empty($item['pricingNote'])): ?>
            <br><em><?= Html::encode($item['pricingNote']) ?></em>
        <?php endif; ?>
        <?php if (isset($item['comment'])): ?>
            <br><em><?= Html::encode($item['comment']) ?></em>
        <?php endif; ?>
        <?php if (!empty($item['hasAttachment'])): ?>
            <br><em>Есть вложение (файл в админке заказа)</em>
        <?php endif; ?>
    </li>
<?php endforeach; ?>
</ol>

<?php if ($adminOrderUrl !== null): ?>
    <p><a href="<?= Html::encode($adminOrderUrl) ?>">Открыть заказ в админке</a></p>
<?php endif; ?>

<p style="color:#666;font-size:12px;">Создан: <?= Html::encode($createdAt) ?></p>
