<?php

namespace app\services\order;

use Yii;

final class OrderPaymentMapper
{
    public const METHOD_CASH = 'cash';
    public const METHOD_CASHLESS = 'cashless';
    public const METHOD_ONLINE = 'online';

    public const PAYMENT_STATUS_PENDING = 'pending';
    public const PAYMENT_STATUS_PAID = 'paid';
    public const PAYMENT_STATUS_FAILED = 'failed';
    public const PAYMENT_STATUS_CANCELLED = 'cancelled';

    /**
     * @return array<string, string>
     */
    public static function methodLabels(): array
    {
        return [
            self::METHOD_CASH => 'Наличные',
            self::METHOD_CASHLESS => 'Безналичная оплата',
            self::METHOD_ONLINE => 'Онлайн-оплата',
        ];
    }

    public static function normalizeMethod(?string $method): ?string
    {
        $method = strtolower(trim((string)$method));
        if ($method === '') {
            return null;
        }

        return array_key_exists($method, self::methodLabels()) ? $method : null;
    }

    public static function paymentLabel(?string $method): ?string
    {
        if ($method === null || trim($method) === '') {
            return null;
        }

        return self::methodLabels()[$method] ?? null;
    }

    public static function cashlessSurchargeAmount(?string $method, float $subtotal): float
    {
        if ($method !== self::METHOD_CASHLESS || $subtotal <= 0) {
            return 0.0;
        }

        $percent = (float)(Yii::$app->params['orderCashlessSurchargePercent'] ?? 0);
        if ($percent <= 0) {
            return 0.0;
        }

        return round($subtotal * $percent / 100, 2);
    }

    public static function paymentStatusLabel(?string $status): ?string
    {
        if ($status === null || trim($status) === '') {
            return null;
        }

        return match ($status) {
            self::PAYMENT_STATUS_PENDING => 'Ожидает оплату',
            self::PAYMENT_STATUS_PAID => 'Оплачен',
            self::PAYMENT_STATUS_FAILED => 'Ошибка оплаты',
            self::PAYMENT_STATUS_CANCELLED => 'Оплата отменена',
            default => null,
        };
    }

    /** Куда вести UI после опроса: success = остаться / показать итог; cart = paymentCartReturnUrl. */
    public static function paymentReturnTarget(?string $paymentStatus): ?string
    {
        if ($paymentStatus === null || trim($paymentStatus) === '') {
            return null;
        }

        return match ($paymentStatus) {
            self::PAYMENT_STATUS_PENDING => 'success',
            self::PAYMENT_STATUS_PAID,
            self::PAYMENT_STATUS_CANCELLED,
            self::PAYMENT_STATUS_FAILED => 'success',
            default => null,
        };
    }
}
