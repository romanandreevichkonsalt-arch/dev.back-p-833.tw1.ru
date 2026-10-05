<?php

namespace app\services\payment;

/**
 * Dev/test helpers for YooKassa (demo store, local QA).
 */
final class YooKassaDevSupport
{
    /**
     * Test simulate endpoints are allowed when secret starts with test_ or YOOKASSA_TEST_ENDPOINTS=1.
     *
     * @param array<string, mixed> $yookassaParams
     */
    public static function testEndpointsEnabled(array $yookassaParams): bool
    {
        $flag = getenv('YOOKASSA_TEST_ENDPOINTS');
        if ($flag !== false && $flag !== '') {
            return filter_var($flag, FILTER_VALIDATE_BOOLEAN);
        }

        $key = trim((string)($yookassaParams['secretKey'] ?? ''));

        return str_starts_with($key, 'test_');
    }

    /**
     * @param array<string, mixed> $yookassaParams
     */
    public static function assertTestRequestAllowed(array $yookassaParams): void
    {
        if (!self::testEndpointsEnabled($yookassaParams)) {
            throw new \yii\web\NotFoundHttpException('Not found.');
        }

        $expected = trim((string)($yookassaParams['testApiToken'] ?? ''));
        if ($expected === '') {
            return;
        }

        $provided = trim((string)(\Yii::$app->request->headers->get('X-Yookassa-Test-Token', '')));
        if ($provided === '' || !hash_equals($expected, $provided)) {
            throw new \yii\web\ForbiddenHttpException('Invalid test token.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function scenariosPayload(): array
    {
        return [
            'description' => 'Демо-магазин ЮKassa: тестовые карты и dev-endpoints симуляции webhook без формы оплаты.',
            'documentationUrl' => 'https://yookassa.ru/developers/payment-acceptance/testing-and-going-live/testing',
            'projectDoc' => '/docs/yookassa-testing.md',
            'endpoints' => [
                [
                    'method' => 'GET',
                    'path' => '/api/v1/payments/yookassa/test/scenarios',
                    'summary' => 'Справка: карты и сценарии',
                ],
                [
                    'method' => 'POST',
                    'path' => '/api/v1/payments/yookassa/test/succeed',
                    'summary' => 'Симуляция payment.succeeded (очистка корзины гостя)',
                    'body' => ['orderNumber' => 'ORD-…'],
                ],
                [
                    'method' => 'POST',
                    'path' => '/api/v1/payments/yookassa/test/cancel',
                    'summary' => 'Симуляция payment.canceled (отказ / отмена)',
                    'body' => ['orderNumber' => 'ORD-…'],
                ],
                [
                    'method' => 'POST',
                    'path' => '/api/v1/payments/yookassa/test/insufficient-funds',
                    'summary' => 'То же, что cancel (сценарий «нет денег» на форме ЮKassa)',
                    'body' => ['orderNumber' => 'ORD-…'],
                ],
            ],
            'testCards' => [
                'success' => [
                    ['number' => '5555555555554444', 'brand' => 'Mastercard', 'note' => 'Успех без 3-D Secure'],
                    ['number' => '4111111111111111', 'brand' => 'Visa', 'note' => 'Успех без 3-D Secure'],
                ],
                'insufficientFunds' => [
                    ['number' => '5555555555554600', 'brand' => 'Mastercard'],
                    ['number' => '4562655587712390', 'brand' => 'Visa'],
                    ['number' => '2200000000000053', 'brand' => 'Mir'],
                ],
            ],
            'realCheckoutNote' => 'На форме ЮKassa используйте карты из insufficientFunds; симуляция API дублирует итог заказа без оплаты.',
            'receiptNote' => 'POST /orders для гостя: paymentReceiptIncluded=true. Логи: YooKassa receipt included/skipped.',
        ];
    }
}
