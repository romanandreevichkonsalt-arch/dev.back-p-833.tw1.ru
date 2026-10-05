<?php

namespace app\services\payment;

use Yii;

/**
 * Structured request/response logging for YooKassa API (secrets never logged).
 */
final class YooKassaHttpLogger
{
    public const CATEGORY_HTTP = 'yookassa.http';
    public const CATEGORY_WEBHOOK = 'yookassa.webhook';
    public const CATEGORY_FLOW = 'yookassa.flow';

    /**
     * @param array<string, mixed>|null $body
     */
    public static function logOutbound(
        string $shopId,
        string $method,
        string $path,
        ?array $body = null,
        ?string $idempotenceKey = null,
    ): void {
        $payload = [
            'direction' => 'out',
            'shopId' => $shopId,
            'method' => $method,
            'path' => $path,
        ];
        if ($idempotenceKey !== null && $idempotenceKey !== '') {
            $payload['idempotenceKey'] = $idempotenceKey;
        }
        if ($body !== null) {
            $payload['body'] = self::sanitizeBody($body);
        }

        self::write(self::CATEGORY_HTTP, $payload);
    }

    /**
     * @param array<string, mixed>|null $decoded
     */
    public static function logInbound(
        string $shopId,
        string $method,
        string $path,
        int $httpStatus,
        ?array $decoded,
        ?string $curlError = null,
        bool $retry = false,
    ): void {
        $payload = [
            'direction' => 'in',
            'shopId' => $shopId,
            'method' => $method,
            'path' => $path,
            'httpStatus' => $httpStatus,
            'retry' => $retry,
        ];
        if ($curlError !== null && $curlError !== '') {
            $payload['curlError'] = $curlError;
        }
        if ($decoded !== null) {
            $payload['body'] = self::sanitizeBody($decoded);
        }

        self::write(self::CATEGORY_HTTP, $payload);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function logWebhook(string $phase, array $data): void
    {
        self::write(self::CATEGORY_WEBHOOK, array_merge(['phase' => $phase], $data));
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function logFlow(string $message, array $data = []): void
    {
        self::write(self::CATEGORY_FLOW, array_merge(['message' => $message], $data));
    }

    public static function maskSecretKey(string $secretKey): string
    {
        $secretKey = trim($secretKey);
        if ($secretKey === '') {
            return '(empty)';
        }
        $prefix = str_starts_with($secretKey, 'test_') ? 'test_' : (str_starts_with($secretKey, 'live_') ? 'live_' : '');
        $tail = strlen($secretKey) >= 4 ? substr($secretKey, -4) : '****';

        return $prefix . '****' . $tail;
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public static function configSummary(array $params): array
    {
        $shopId = trim((string)($params['shopId'] ?? ''));
        $secretKey = trim((string)($params['secretKey'] ?? ''));

        return [
            'shopId' => $shopId !== '' ? $shopId : null,
            'secretKeyMasked' => self::maskSecretKey($secretKey),
            'isTestSecret' => str_starts_with($secretKey, 'test_'),
            'expectedDevTestShopId' => '1470173',
            'shopIdMatchesExpectedDevTest' => $shopId === '1470173',
            'returnUrl' => trim((string)($params['returnUrl'] ?? '')) ?: null,
            'sendReceipt' => ($params['sendReceipt'] ?? true) !== false,
            'receiptDestinationEmail' => trim((string)($params['receiptDestinationEmail'] ?? '')) ?: null,
            'receiptTaxSystemCode' => (int)($params['receiptTaxSystemCode'] ?? 1),
            'receiptPaymentMode' => trim((string)($params['receiptPaymentMode'] ?? 'full_prepayment')) ?: 'full_prepayment',
            'receiptTimezone' => (int)($params['receiptTimezone'] ?? 3),
            'receiptFallbackEmailDomain' => trim((string)($params['receiptFallbackEmailDomain'] ?? '')) ?: null,
            'logFiles' => [
                'yookassa' => 'runtime/logs/yookassa.log',
                'app' => 'runtime/logs/app.log',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    private static function sanitizeBody(array $body): array
    {
        $json = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return ['_error' => 'json_encode failed'];
        }
        if (strlen($json) > 8000) {
            return [
                '_truncated' => true,
                'preview' => substr($json, 0, 8000),
            ];
        }

        return $body;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function write(string $category, array $payload): void
    {
        $line = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($line === false) {
            $line = '{"_error":"log json_encode failed"}';
        }
        Yii::info($line, $category);
    }
}
