<?php

/** @var string $companyName */
/** @var string $amount */
/** @var string $expiresAt */
/** @var string $cabinetUrl */

$greeting = $companyName !== '' ? 'Здравствуйте, ' . $companyName . '!' : 'Здравствуйте!';

echo $greeting . "\n\n";
echo 'Напоминаем: ' . $amount . ' ₽ кэшбека сгорят ' . $expiresAt . ".\n\n";
echo "Используйте кэшбек при оформлении следующего заказа в личном кабинете дилера.\n\n";

if ($cabinetUrl !== '') {
    echo "Личный кабинет: {$cabinetUrl}\n\n";
}

echo "Если вы уже использовали кэшбек, проигнорируйте это письмо.\n";
