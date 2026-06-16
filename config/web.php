<?php

use app\services\SmsSenderInterface;
use app\services\SmsSenderStub;
use app\services\YandexIdService;
use app\services\YandexIdServiceInterface;

$params = require __DIR__ . '/params.php';
$db = require __DIR__ . '/db.php';

$config = [
    'id' => 'basic',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
    ],
    'components' => [
        'request' => [
            // !!! insert a secret key in the following (if it is empty) - this is required by cookie validation
            'cookieValidationKey' => 'z3Y32_AeD7OA-IyXENvpHo9_ooPY9eG2',
            'parsers' => [
                'application/json' => 'yii\web\JsonParser',
            ],
        ],
        'cache' => [
            'class' => 'yii\caching\FileCache',
        ],
        'user' => [
            'identityClass' => 'app\models\User',
            'enableAutoLogin' => true,
            'loginUrl' => ['site/login'],
        ],
        'errorHandler' => [
            'errorAction' => 'site/error',
        ],
        'mailer' => [
            'class' => \yii\symfonymailer\Mailer::class,
            'viewPath' => '@app/mail',
            // send all mails to a file by default.
            'useFileTransport' => true,
        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning'],
                ],
            ],
        ],
        'db' => $db,
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'rules' => [
                'OPTIONS api/v1/<path:.+>' => 'api/v1/options/preflight',
                'POST api/v1/auth/request-code' => 'api/v1/auth/request-code',
                'POST api/v1/auth/verify-code' => 'api/v1/auth/verify-code',
                'POST api/v1/auth/yandex' => 'api/v1/auth/yandex-login',
                'GET api/v1/ping' => 'api/v1/ping/index',
                'GET api/v1/profile/me' => 'api/v1/profile/me',
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
                'GET swagger/json-schema' => 'swagger/json-schema',
            ],
        ],
    ],
    'container' => [
        'singletons' => [
            SmsSenderInterface::class => SmsSenderStub::class,
            YandexIdServiceInterface::class => static function () use ($params): YandexIdServiceInterface {
                return new YandexIdService($params['yandexId'] ?? []);
            },
        ],
    ],
    'modules' => [
        'admin' => [
            'class' => 'app\modules\admin\Module',
        ],
    ],
    'params' => $params,
];

if (YII_ENV_DEV) {
    // configuration adjustments for 'dev' environment
    $config['bootstrap'][] = 'debug';
    $config['modules']['debug'] = [
        'class' => 'yii\debug\Module',
        // uncomment the following to add your IP if you are not connecting from localhost.
        //'allowedIPs' => ['127.0.0.1', '::1'],
    ];

    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = [
        'class' => 'yii\gii\Module',
        // uncomment the following to add your IP if you are not connecting from localhost.
        //'allowedIPs' => ['127.0.0.1', '::1'],
    ];
}

return $config;
