<?php

namespace app\services\order;

use app\models\Order;

final class OrderUiMapper
{
    public static function uiStatus(string $status): string
    {
        return match ($status) {
            Order::STATUS_PENDING_PAYMENT, Order::STATUS_NEW, Order::STATUS_CONFIRMED => 'processing',
            Order::STATUS_PRODUCTION, Order::STATUS_READY => 'in_work',
            Order::STATUS_SHIPPING => 'delivery',
            Order::STATUS_COMPLETED => 'completed',
            Order::STATUS_CANCELLED => 'cancelled',
            default => 'processing',
        };
    }

    public static function progressStep(string $status): ?int
    {
        return match ($status) {
            Order::STATUS_PENDING_PAYMENT, Order::STATUS_NEW, Order::STATUS_CONFIRMED => 1,
            Order::STATUS_PRODUCTION, Order::STATUS_READY => 2,
            Order::STATUS_SHIPPING => 3,
            Order::STATUS_COMPLETED => 4,
            default => null,
        };
    }
}
