<?php

$dbDsn = getenv('DB_DSN');
$dbUser = getenv('DB_USER');
$dbPass = getenv('DB_PASSWORD');

return [
    'class' => 'yii\db\Connection',
    'dsn' => is_string($dbDsn) && $dbDsn !== '' ? $dbDsn : 'mysql:host=127.0.0.1;dbname=fabrica',
    'username' => is_string($dbUser) && $dbUser !== '' ? $dbUser : 'root',
    'password' => is_string($dbPass) ? $dbPass : '',
    'charset' => 'utf8mb4',
];
