<?php

$receiptDestinationEmail = trim(getenv('YOOKASSA_RECEIPT_EMAIL') ?: 'tania22gerasimenko@gmail.com');

return [
    'adminEmail' => 'admin@example.com',
    'senderEmail' => 'noreply@example.com',
    'senderName' => 'Example.com mailer',
    'leadsNotifyEmail' => 'mebelanna@internet.ru',
    'yookassa' => [
        'shopId' => getenv('YOOKASSA_SHOP_ID') ?: '',
        'secretKey' => getenv('YOOKASSA_SECRET_KEY') ?: '',
        'returnUrl' => getenv('YOOKASSA_RETURN_URL') ?: '',
        // Чеки 54-ФЗ: receipt.customer.email в POST /payments (все чеки — на этот адрес, если задан).
        'receiptDestinationEmail' => $receiptDestinationEmail,
        'sendReceipt' => filter_var(
            getenv('YOOKASSA_SEND_RECEIPT') ?: ($receiptDestinationEmail !== '' ? '1' : '0'),
            FILTER_VALIDATE_BOOLEAN,
        ),
        'receiptVatCode' => (int)(getenv('YOOKASSA_RECEIPT_VAT_CODE') ?: 1),
        'receiptTaxSystemCode' => (int)(getenv('YOOKASSA_RECEIPT_TAX_SYSTEM_CODE') ?: 1),
        'receiptMeasure' => getenv('YOOKASSA_RECEIPT_MEASURE') ?: 'piece',
        // full_prepayment при создании платежа — см. доку «Чеки от ЮKassa»
        'receiptPaymentMode' => getenv('YOOKASSA_RECEIPT_PAYMENT_MODE') ?: 'full_prepayment',
        'receiptInternet' => filter_var(getenv('YOOKASSA_RECEIPT_INTERNET') ?: '1', FILTER_VALIDATE_BOOLEAN),
        'receiptTimezone' => (int)(getenv('YOOKASSA_RECEIPT_TIMEZONE') ?: 3),
        // Если в форме нет email — чек ЮKassa на guest+{phone}@этот-домен (см. docs/yookassa-testing.md)
        'receiptFallbackEmailDomain' => getenv('YOOKASSA_RECEIPT_FALLBACK_EMAIL_DOMAIN') ?: 'noreply.dev.front-p-833.tw1.ru',
        // Optional: X-Yookassa-Test-Token for POST/GET .../payments/yookassa/test/*
        'testApiToken' => getenv('YOOKASSA_TEST_API_TOKEN') ?: '',
        // https://yookassa.ru/developers/using-api/webhooks#security
        'webhookIpCidrs' => [
            '185.71.76.0/27',
            '185.71.77.0/27',
            '77.75.153.0/25',
            '77.75.154.128/25',
            '77.75.156.11',
            '77.75.156.35',
            '2a02:5180::/32',
        ],
    ],
    // Неоплаченные guest-заказы: cron `php yii order/cancel-unpaid`
    'orderUnpaidCancelHours' => 24,
    'dealerCabinetUrl' => 'https://dev.back-p-833.tw1.ru/dealer',
    'frontendUrl' => getenv('FRONTEND_URL') ?: 'https://dev.front-p-833.tw1.ru',
    'moodboardSharePathPrefix' => '/moodboard/public/',
    'orderAttachmentStoragePath' => '@runtime/order-attachments',
    'leadAttachmentStoragePath' => '@runtime/lead-attachments',
    'leadAttachmentMaxBytes' => 20 * 1024 * 1024,
    'cartAttachmentStoragePath' => '@runtime/cart-attachments',
    'orderAttachmentMaxBytes' => 50 * 1024 * 1024,
    'orderDocumentStoragePath' => '@runtime/order-documents',
    'orderDocumentMaxBytes' => 50 * 1024 * 1024,
    'orderCashlessSurchargePercent' => 0,
    'dealerDefaultDiscountPercent' => 0.0,
    'dealerPriceListMaxBytes' => 50 * 1024 * 1024,
    'dealerDefaultAssignedManager' => [
        'name' => 'Менеджер МФ Анна',
        'role' => 'Менеджер заказов',
        'phone' => null,
        'email' => null,
        'hours' => 'Пн–Пт 9:00–18:00',
        'avatar' => null,
    ],
    'dealerPriceList' => [
        'label' => 'Прайс-лист',
        'filenameMatch' => 'price',
    ],
    'catalogMenuProductsPerPage' => 4,
    'catalogMenuProductsMinPerPage' => 1,
    'catalogMenuProductsMaxPerPage' => 4,
    'mediaStoragePath' => '@webroot/uploads/media',
    'mediaPublicPrefix' => 'uploads/media',
    'mediaImageVariants' => [
        'mediumMaxWidth' => 1200,
        'largeMaxWidth' => 1920,
        'miniMaxWidth' => 200,
        'webpQuality' => 90,
        'largeWebpQuality' => 96,
        'largeWebpQualityNoResize' => 98,
        'largeFileWarningBytes' => 20 * 1024 * 1024,
    ],
    'catalogListingTile' => [
        'width' => 458,
        'height' => 347,
        'background' => '#fcfbf2',
        'floorGuideFromBottom' => 75,
        'miniMaxWidth' => 200,
        'webpQuality' => 90,
    ],
    'apiCache' => [
        'defaultTtl' => 300,
        'catalogMenuTtl' => 900,
        'catalogProductsTtl' => 300,
        // Search index: soft TTL triggers background refresh; hard keeps stale serveable.
        'searchIndexSoftTtl' => 900,
        'searchIndexHardTtl' => 86400,
        'pageTtl' => 900,
    ],
    'corsOrigins' => [
        'http://localhost:3000',
        'http://localhost:5173',
        'http://127.0.0.1:5173',
        'https://dev.back-p-833.tw1.ru',
        'https://dev.front-p-833.tw1.ru',
    ],
    'smsCodeTtl' => 60,
    'smsCodeLength' => 4,
    'yandexId' => [
        'userinfoUrl' => 'https://login.yandex.ru/info?format=json',
    ],
    'content' => [
        'jsonFallback' => false,
    ],
    'fabricImport' => [
        'mailruPublicRoot' => '',
    ],
    'fabricLibraryArchive' => [
        'relativePath' => 'files/library-fabrics.pdf',
    ],
    'dadataApiKey' => getenv('DADATA_API_KEY') ?: '',
    'dadataSecretKey' => getenv('DADATA_SECRET_KEY') ?: '',
    'cashback' => [
        'expiryDays' => 90,
        'notifyDaysBefore' => 7,
        'maxOrderSpendPercent' => 50,
    ],
    // Crontab на prod (MSK): 0 3 1 * * php yii cashback/accrue-monthly
    //                       0 4 * * * php yii cashback/expire
    //                       0 9 * * * php yii cashback/notify-expiring
    'cashbackCron' => [
        'accrueMonthly' => '0 3 1 * *',
        'expire' => '0 4 * * *',
        'notifyExpiring' => '0 9 * * *',
    ],
];
