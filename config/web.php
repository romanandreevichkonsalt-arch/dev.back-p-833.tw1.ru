<?php

use app\services\catalog\CatalogService;
use app\services\content\JsonContentService;
use app\services\content\PageContentService;
use app\services\search\SearchService;
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
        'adminUser' => [
            'class' => 'yii\web\User',
            'identityClass' => 'app\models\AdminUser',
            'enableAutoLogin' => true,
            'identityCookie' => [
                'name' => '_adminUser',
                'httpOnly' => true,
            ],
            'loginUrl' => ['/admin/site/login'],
        ],
        'errorHandler' => [
            'class' => 'app\components\ApiErrorHandler',
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
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['info', 'warning', 'error'],
                    'categories' => [
                        'yookassa.http',
                        'yookassa.webhook',
                        'yookassa.flow',
                    ],
                    'logFile' => '@runtime/logs/yookassa.log',
                    'maxFileSize' => 10240,
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
                'PUT api/v1/profile/subscription' => 'api/v1/profile/subscription',
                'POST api/v1/leads' => 'api/v1/leads/create',
                'GET api/v1/pages/home' => 'api/v1/pages/home',
                'GET api/v1/pages/partners' => 'api/v1/pages/partners',
                'GET api/v1/pages/designers' => 'api/v1/pages/designers',
                'GET api/v1/pages/contacts' => 'api/v1/pages/contacts',
                'GET api/v1/pages/legal-documents' => 'api/v1/pages/legal-documents',
                'GET api/v1/pages/faq' => 'api/v1/pages/faq',
                'GET api/v1/pages/journal' => 'api/v1/pages/journal',
                'GET api/v1/pages/journal/<slug>' => 'api/v1/pages/journal-article',
                'GET api/v1/journal/articles/<slug>' => 'api/v1/journal/article',
                'GET api/v1/pages/building' => 'api/v1/pages/building',
                'GET api/v1/pages/about' => 'api/v1/pages/about',
                'GET api/v1/pages/vacancies' => 'api/v1/pages/vacancies',
                'GET api/v1/pages/vacancies/<slug>' => 'api/v1/pages/vacancy-article',
                'GET api/v1/vacancies/<slug>' => 'api/v1/vacancies/view',
                'HEAD api/v1/catalog/menu' => 'api/v1/catalog/menu',
                'GET api/v1/catalog/menu' => 'api/v1/catalog/menu',
                'HEAD api/v1/catalog/menu/products' => 'api/v1/catalog/menu-products',
                'GET api/v1/catalog/menu/products' => 'api/v1/catalog/menu-products',
                'HEAD api/v1/catalog/menu/<slugs:.+>' => 'api/v1/catalog/menu',
                'GET api/v1/catalog/menu/<slugs:.+>' => 'api/v1/catalog/menu',
                'HEAD api/v1/catalog/navigation' => 'api/v1/catalog/navigation',
                'GET api/v1/catalog/navigation' => 'api/v1/catalog/navigation',
                'GET api/v1/catalog/products/<slug:[\\w-]+>' => 'api/v1/catalog/product',
                'POST api/v1/catalog/models/<slug:[\\w-]+>/3d-file' => 'api/v1/catalog/upload-model-3d-file',
                'HEAD api/v1/catalog/library-products' => 'api/v1/catalog/library-products',
                'GET api/v1/catalog/library-products' => 'api/v1/catalog/library-products',
                'HEAD api/v1/catalog/library-fabrics' => 'api/v1/catalog/library-fabrics',
                'GET api/v1/catalog/library-fabrics' => 'api/v1/catalog/library-fabrics',
                'HEAD api/v1/catalog/products' => 'api/v1/catalog/products',
                'GET api/v1/catalog/products' => 'api/v1/catalog/products',
                'HEAD api/v1/catalog/filters' => 'api/v1/catalog/filters',
                'GET api/v1/catalog/filters' => 'api/v1/catalog/filters',
                'GET api/v1/search/bootstrap' => 'api/v1/search/bootstrap',
                'GET api/v1/search/products' => 'api/v1/search/products',
                'GET api/v1/search' => 'api/v1/search/index',
                'GET api/v1/cart' => 'api/v1/cart/index',
                'POST api/v1/cart/items' => 'api/v1/cart/add-item',
                'PATCH api/v1/cart/items/<productId:[\\w-]+>' => 'api/v1/cart/update-item',
                'PATCH api/v1/cart/items/<productId:[\\w-]+>/comment' => 'api/v1/cart/update-item-comment',
                'POST api/v1/cart/items/<productId:[\\w-]+>/attachment' => 'api/v1/cart/upload-item-attachment',
                'GET api/v1/cart/items/<productId:[\\w-]+>/attachment' => 'api/v1/cart/download-item-attachment',
                'DELETE api/v1/cart/items/<productId:[\\w-]+>/attachment' => 'api/v1/cart/remove-item-attachment',
                'DELETE api/v1/cart/items/<productId:[\\w-]+>' => 'api/v1/cart/remove-item',
                'POST api/v1/cart/sync' => 'api/v1/cart/sync',
                'GET api/v1/orders' => 'api/v1/order/index',
                'POST api/v1/orders' => 'api/v1/order/create',
                'GET api/v1/orders/<number:[\\w-]+>/payment-status' => 'api/v1/order/payment-status',
                'GET api/v1/orders/<number:[\\w-]+>/items/<productId:[\\w-]+>/attachment' => 'api/v1/order/download-item-attachment',
                'GET api/v1/orders/<number:[\\w-]+>/documents/<documentId:\\d+>' => 'api/v1/order/download-document',
                'GET api/v1/orders/<number:[\\w-]+>' => 'api/v1/order/view',
                'POST api/v1/payments/yookassa/webhook' => 'api/v1/payment/yookassa-webhook',
                'GET api/v1/payments/yookassa/test/scenarios' => 'api/v1/payment/yookassa-test-scenarios',
                'POST api/v1/payments/yookassa/test/succeed' => 'api/v1/payment/yookassa-test-succeed',
                'POST api/v1/payments/yookassa/test/cancel' => 'api/v1/payment/yookassa-test-cancel',
                'POST api/v1/payments/yookassa/test/insufficient-funds' => 'api/v1/payment/yookassa-test-insufficient-funds',
                'POST api/v1/dealer/auth/login' => 'api/v1/dealer-auth/login',
                'POST api/v1/dealer/auth/logout' => 'api/v1/dealer-auth/logout',
                'GET api/v1/dealer/profile' => 'api/v1/dealer-profile/index',
                'PUT api/v1/dealer/profile' => 'api/v1/dealer-profile/update',
                'GET api/v1/dealer/bonuses' => 'api/v1/dealer-bonuses/index',
                'GET api/v1/dealer/promotions/banners' => 'api/v1/dealer-promotions/banners',
                'GET api/v1/dealer/promotions/popup' => 'api/v1/dealer-promotions/popup',
                'GET api/v1/dealer/promotions/sales' => 'api/v1/dealer-promotions/sales',
                'GET api/v1/dealer/price-list' => 'api/v1/dealer-price-list/index',
                'GET api/v1/dealer/tech-photos' => 'api/v1/dealer-tech-photos/index',
                'POST api/v1/dealer/password' => 'api/v1/dealer-password/update',
                'POST api/v1/cart/promo' => 'api/v1/cart/apply-promo',
                'DELETE api/v1/cart/promo' => 'api/v1/cart/remove-promo',
                'PATCH api/v1/cart/cashback' => 'api/v1/cart/apply-cashback',
                'DELETE api/v1/cart/cashback' => 'api/v1/cart/remove-cashback',
                'POST api/v1/favorites/add' => 'api/v1/favorites/add',
                'POST api/v1/favorites/remove' => 'api/v1/favorites/remove',
                'POST api/v1/favorites/check' => 'api/v1/favorites/check',
                'GET api/v1/favorites/list' => 'api/v1/favorites/list',
                'POST api/v1/favorites/sync' => 'api/v1/favorites/sync',
                'POST api/v1/dadata/suggest/city' => 'api/v1/da-data/suggest-city',
                'POST api/v1/dadata/suggest/address' => 'api/v1/da-data/suggest-address',
                'GET api/v1/moodboard/picker/bootstrap' => 'api/v1/moodboard/picker-bootstrap',
                'GET api/v1/moodboard/picker/categories' => 'api/v1/moodboard/picker-categories',
                'GET api/v1/moodboard/picker/colors' => 'api/v1/moodboard/picker-colors',
                'GET api/v1/moodboard/picker/models' => 'api/v1/moodboard/picker-models',
                'GET api/v1/moodboard/picker/fabrics' => 'api/v1/moodboard/picker-fabrics',
                'GET api/v1/moodboard/picker/surface-materials' => 'api/v1/moodboard/picker-surface-materials',
                'GET api/v1/moodboard/object-types' => 'api/v1/moodboard/object-types',
                'GET api/v1/moodboard/boards' => 'api/v1/moodboard/list-boards',
                'POST api/v1/moodboard/boards' => 'api/v1/moodboard/create-board',
                'POST api/v1/moodboard/uploads/cover' => 'api/v1/moodboard/upload-cover',
                'GET api/v1/moodboard/public/<code:[\\w-]+>' => 'api/v1/moodboard/view-public-board',
                'POST api/v1/moodboard/boards/sync' => 'api/v1/moodboard/sync-boards',
                'GET api/v1/moodboard/boards/<id:[\\w-]+>' => 'api/v1/moodboard/view-board',
                'PUT api/v1/moodboard/boards/<id:[\\w-]+>' => 'api/v1/moodboard/update-board',
                'PATCH api/v1/moodboard/boards/<id:[\\w-]+>' => 'api/v1/moodboard/patch-board',
                'DELETE api/v1/moodboard/boards/<id:[\\w-]+>' => 'api/v1/moodboard/delete-board',
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
            JsonContentService::class => JsonContentService::class,
            CatalogService::class => CatalogService::class,
            \app\services\catalog\CatalogModelProductSyncService::class => \app\services\catalog\CatalogModelProductSyncService::class,
            \app\services\catalog\CatalogPriceCategoryService::class => \app\services\catalog\CatalogPriceCategoryService::class,
            \app\services\media\MediaUrlResolver::class => \app\services\media\MediaUrlResolver::class,
            \app\services\cache\ApiResponseCache::class => \app\services\cache\ApiResponseCache::class,
            PageContentService::class => PageContentService::class,
            \app\services\journal\JournalArticleService::class => \app\services\journal\JournalArticleService::class,
            \app\services\vacancy\VacancyService::class => \app\services\vacancy\VacancyService::class,
            SearchService::class => SearchService::class,
            \app\services\cart\CartService::class => \app\services\cart\CartService::class,
            \app\services\order\OrderApiService::class => \app\services\order\OrderApiService::class,
            \app\services\dealer\DealerRegistrationService::class => \app\services\dealer\DealerRegistrationService::class,
            \app\services\dealer\DealerAuthService::class => \app\services\dealer\DealerAuthService::class,
            \app\services\dealer\DealerProfileService::class => \app\services\dealer\DealerProfileService::class,
            \app\services\favorites\FavoritesService::class => \app\services\favorites\FavoritesService::class,
            \app\services\guest\GuestDataSyncService::class => \app\services\guest\GuestDataSyncService::class,
            \app\services\guest\GuestSessionService::class => \app\services\guest\GuestSessionService::class,
        ],
    ],
    'modules' => [
        'admin' => [
            'class' => 'app\modules\admin\Module',
            'defaultRoute' => 'dashboard',
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
