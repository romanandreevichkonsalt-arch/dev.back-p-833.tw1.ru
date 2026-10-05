<?php

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
/** @var list<array{title: string, quantity: int, lineTotal: string, comment?: string, hasAttachment?: bool}> $items */
/** @var string $createdAt */

echo "Новый заказ от дилера\n\n";
echo "Номер: {$orderNumber}\n";
echo "Создан: {$createdAt}\n\n";

echo "Дилер:\n";
echo '  Компания: ' . ($dealerCompanyName !== '' ? $dealerCompanyName : '—') . "\n";
echo '  ИНН: ' . ($dealerInn !== '' ? $dealerInn : '—') . "\n";
echo "  Логин: {$dealerUsername}\n\n";

echo "Клиент заказа:\n";
echo "  {$customerName}\n";
echo "  {$customerPhone}\n";
if ($customerEmail !== null && trim($customerEmail) !== '') {
    echo "  {$customerEmail}\n";
}
if ($deliveryAddress !== null && trim($deliveryAddress) !== '') {
    echo "  Адрес: {$deliveryAddress}\n";
}
echo "\n";

echo "Оплата: {$paymentLabel}\n";
if ($retailSubtotalAmount !== null) {
    echo "РРЦ: {$retailSubtotalAmount} ₽\n";
}
if ($dealerPersonalDiscountAmount !== null) {
    echo "Скидка дилера: −{$dealerPersonalDiscountAmount} ₽\n";
}
if ($catalogPromotionDiscountAmount !== null) {
    echo "Акции каталога: −{$catalogPromotionDiscountAmount} ₽\n";
}
echo "Сумма после цен дилера: {$subtotalAmount} ₽\n";
if ($promoDiscountAmount !== null) {
    echo 'Скидка по промокоду' . ($promoCode !== null ? " ({$promoCode})" : '') . ": −{$promoDiscountAmount} ₽\n";
}
if ($cashbackUsedAmount !== null) {
    echo "Списано кэшбека: −{$cashbackUsedAmount} ₽\n";
}
if ($cashlessSurchargeAmount !== null) {
    echo "Наценка безнал: +{$cashlessSurchargeAmount} ₽\n";
}
echo "Итого: {$totalAmount} ₽\n\n";

echo "Позиции:\n";
foreach ($items as $index => $item) {
    $n = $index + 1;
    echo "{$n}. {$item['title']} × {$item['quantity']} — РРЦ {$item['retailLineTotal']} ₽, строка {$item['lineTotal']} ₽, к оплате {$item['paidLineTotal']} ₽";
    if (!empty($item['pricingNote'])) {
        echo "\n   {$item['pricingNote']}";
    }
    if (isset($item['comment'])) {
        echo "\n   Комментарий: {$item['comment']}";
    }
    if (!empty($item['hasAttachment'])) {
        echo "\n   Есть вложение (файл в админке заказа)";
    }
    echo "\n";
}

if ($adminOrderUrl !== null) {
    echo "\nОткрыть в админке: {$adminOrderUrl}\n";
}
