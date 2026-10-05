<?php

namespace app\services\payment;

use Yii;
use yii\base\InvalidConfigException;

/**
 * Minimal HTTP client for YooKassa Payments API v3 (no SDK dependency).
 *
 * @see https://yookassa.ru/developers/api
 */
class YooKassaClient
{
    private const API_BASE = 'https://api.yookassa.ru/v3';

    private string $shopId;
    private string $secretKey;

    public function __construct(?string $shopId = null, ?string $secretKey = null)
    {
        $params = Yii::$app->params['yookassa'] ?? [];
        $this->shopId = trim((string)($shopId ?? $params['shopId'] ?? ''));
        $this->secretKey = trim((string)($secretKey ?? $params['secretKey'] ?? ''));
    }

    public function isConfigured(): bool
    {
        return $this->shopId !== '' && $this->secretKey !== '';
    }

    public function getShopId(): string
    {
        return $this->shopId;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function createPayment(array $payload, string $idempotenceKey): array
    {
        return $this->request('POST', '/payments', $payload, $idempotenceKey);
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayment(string $paymentId): array
    {
        return $this->request('GET', '/payments/' . rawurlencode($paymentId));
    }

    /**
     * @param array<string, mixed>|null $body
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, ?array $body = null, ?string $idempotenceKey = null): array
    {
        if (!$this->isConfigured()) {
            throw new InvalidConfigException('YooKassa credentials are not configured.');
        }

        YooKassaHttpLogger::logOutbound($this->shopId, $method, $path, $body, $idempotenceKey);

        $url = self::API_BASE . $path;
        $headers = [
            'Authorization: Basic ' . base64_encode($this->shopId . ':' . $this->secretKey),
            'Content-Type: application/json',
            'Accept: application/json',
        ];
        if ($idempotenceKey !== null && $idempotenceKey !== '') {
            $headers[] = 'Idempotence-Key: ' . $idempotenceKey;
        }

        $ch = curl_init($url);
        if ($ch === false) {
            throw new \RuntimeException('Failed to init curl for YooKassa request.');
        }

        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
        ];
        if ($body !== null) {
            $opts[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        curl_setopt_array($ch, $opts);
        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $retry = false;

        if (($errno !== 0 || $raw === false) && $errno === CURLE_OPERATION_TIMEDOUT) {
            $retry = true;
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 25);
            $raw = curl_exec($ch);
            $errno = curl_errno($ch);
            $error = curl_error($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        }

        curl_close($ch);

        if ($errno !== 0 || $raw === false) {
            YooKassaHttpLogger::logInbound($this->shopId, $method, $path, $status, null, $error, $retry);
            throw new \RuntimeException('YooKassa HTTP error: ' . $error);
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            YooKassaHttpLogger::logInbound($this->shopId, $method, $path, $status, ['raw' => substr($raw, 0, 500)], null, $retry);
            throw new \RuntimeException('YooKassa returned invalid JSON (HTTP ' . $status . ').');
        }

        YooKassaHttpLogger::logInbound($this->shopId, $method, $path, $status, $decoded, null, $retry);

        if ($status < 200 || $status >= 300) {
            $description = (string)($decoded['description'] ?? $decoded['type'] ?? 'HTTP ' . $status);
            throw new \RuntimeException('YooKassa API error: ' . $description, $status);
        }

        return $decoded;
    }
}
