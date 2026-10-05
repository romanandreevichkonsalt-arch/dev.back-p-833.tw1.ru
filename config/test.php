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
$params['content']['jsonFallback'] = true;
// Never call live YooKassa from unit/api tests.
$params['yookassa'] = [
    'shopId' => '',
    'secretKey' => '',
    'returnUrl' => 'https://front.example/order/success',
];
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
        'request' => [
            'cookieValidationKey' => 'test',
            'enableCsrfValidation' => false,
            'parsers' => [
                'application/json' => 'yii\web\JsonParser',
            ],
        ],
        'errorHandler' => [
            'class' => 'app\components\ApiErrorHandler',
            'errorAction' => 'site/error',
        ],
        'mailer' => [
            'class' => \yii\symfonymailer\Mailer::class,
            'viewPath' => '@app/mail',
            'useFileTransport' => true,
            'messageClass' => 'yii\symfonymailer\Message',
        ],
        'assetManager' => [
            'basePath' => __DIR__ . '/../web/assets',
        ],
        'cache' => [
            'class' => 'yii\caching\FileCache',
        ],
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
                'GET api/v1/moodboard/public/<code:[\\w-]+>' => 'api/v1/moodboard/view-public-board',
                'POST api/v1/moodboard/boards/sync' => 'api/v1/moodboard/sync-boards',
                'GET api/v1/moodboard/boards/<id:[\\w-]+>' => 'api/v1/moodboard/view-board',
                'PUT api/v1/moodboard/boards/<id:[\\w-]+>' => 'api/v1/moodboard/update-board',
                'PATCH api/v1/moodboard/boards/<id:[\\w-]+>' => 'api/v1/moodboard/patch-board',
                'DELETE api/v1/moodboard/boards/<id:[\\w-]+>' => 'api/v1/moodboard/delete-board',
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
            ],
        ],
        'user' => [
            'identityClass' => 'app\models\User',
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
            PageContentService::class => PageContentService::class,
            \app\services\journal\JournalArticleService::class => \app\services\journal\JournalArticleService::class,
            \app\services\vacancy\VacancyService::class => \app\services\vacancy\VacancyService::class,
            SearchService::class => SearchService::class,
            \app\services\cache\ApiResponseCache::class => \app\services\cache\ApiResponseCache::class,
            \app\services\media\MediaUrlResolver::class => \app\services\media\MediaUrlResolver::class,
            \app\services\favorites\FavoritesService::class => \app\services\favorites\FavoritesService::class,
            \app\services\cart\CartService::class => \app\services\cart\CartService::class,
            \app\services\guest\GuestDataSyncService::class => \app\services\guest\GuestDataSyncService::class,
            \app\services\guest\GuestSessionService::class => \app\services\guest\GuestSessionService::class,
        ],
    ],
    'params' => $params,
];
