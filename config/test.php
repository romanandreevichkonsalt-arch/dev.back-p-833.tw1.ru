<?php

$params = require __DIR__ . '/params.php';
$db = require __DIR__ . '/test_db.php';

/**
 * Application configuration shared by all test types
 */
return [
    'id' => 'basic-tests',
    'basePath' => dirname(__DIR__),
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
    ],
    'language' => 'en-US',
    'components' => [
        'db' => $db,
        'mailer' => [
            'class' => \yii\symfonymailer\Mailer::class,
            'viewPath' => '@app/mail',
            // send all mails to a file by default.
            'useFileTransport' => true,
            'messageClass' => 'yii\symfonymailer\Message'
        ],
        'assetManager' => [
            'basePath' => __DIR__ . '/../web/assets',
        ],
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => true,
            'rules' => [
                'OPTIONS api/v1/<path:.+>' => 'api/v1/options/preflight',
                'GET api/v1/categories' => 'api/v1/categories/index',
                'GET api/v1/categories/tree' => 'api/v1/categories/tree',
                'POST api/v1/categories' => 'api/v1/categories/create',
                'GET api/v1/categories/<id:\d+>' => 'api/v1/categories/view',
                'GET api/v1/categories/<id:\d+>/children' => 'api/v1/categories/children',
                'PUT api/v1/categories/<id:\d+>' => 'api/v1/categories/update',
                'PATCH api/v1/categories/<id:\d+>' => 'api/v1/categories/update',
                'DELETE api/v1/categories/<id:\d+>' => 'api/v1/categories/delete',
                'GET api/v1/products' => 'api/v1/products/index',
                'POST api/v1/products' => 'api/v1/products/create',
                'GET api/v1/products/<id:\d+>' => 'api/v1/products/view',
                'PUT api/v1/products/<id:\d+>' => 'api/v1/products/update',
                'PATCH api/v1/products/<id:\d+>' => 'api/v1/products/update',
                'DELETE api/v1/products/<id:\d+>' => 'api/v1/products/delete',
            ],
        ],
        'request' => [
            'cookieValidationKey' => 'test',
            'enableCsrfValidation' => false,
            'parsers' => [
                'application/json' => 'yii\web\JsonParser',
            ],
        ],
        'user' => [
            'identityClass' => 'app\models\User',
        ],
    ],
    'params' => $params,
];
