<?php

/** @var string $companyName */
/** @var string $username */
/** @var string $password */
/** @var string $cabinetUrl */

$greeting = $companyName !== '' ? "Здравствуйте, {$companyName}!" : 'Здравствуйте!';

echo $greeting . "\n\n";
echo "Для вас создан доступ в личный кабинет дилера мебельной фабрики «Анна».\n\n";
echo "Логин: {$username}\n";
echo "Пароль: {$password}\n\n";

if ($cabinetUrl !== '') {
    echo "Ссылка: {$cabinetUrl}\n\n";
}

echo "При первом входе необходимо заполнить профиль: ИНН, мобильный телефон, email и ФИО менеджера.\n\n";
echo "Если вы не запрашивали доступ, проигнорируйте это письмо.\n";
