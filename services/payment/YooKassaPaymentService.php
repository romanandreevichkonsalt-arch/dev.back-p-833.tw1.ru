<?php

namespace app\services\payment;

use app\models\Order;
use app\services\cart\CartService;
use app\services\guest\ApiOwnerContext;
use app\services\order\OrderPaymentMapper;
use Yii;
use yii\base\InvalidConfigException;

class YooKassaPaymentService
{
    public const PROVIDER = 'yookassa';

    public function __construct(
        private readonly YooKassaClient $client = new YooKassaClient(),
        private readonly CartService $cartService = new CartService(),
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->client->isConfigured();
    }

    /**
     * @return array{
     *     externalId: string,
     *     confirmationUrl: string,
     *     returnUrl: string,
     *     status: string,
     *     receiptIncluded: bool,
     *     receiptSkipReason: string|null,
     * }
     */
    public function createRedirectPayment(Order $order): array
    {
        if (!$this->isConfigured()) {
            throw new InvalidConfigException('YooKassa is not configured.');
        }

        $order = Order::find()->where(['id' => (int)$order->id])->with('items')->one() ?? $order;

        $returnUrl = $this->resolveReturnUrl($order);
        $amount = number_format((float)$order->total_amount, 2, '.', '');
        if ((float)$amount <= 0) {
            throw new \InvalidArgumentException('Order total must be greater than zero for online payment.');
        }

        $payload = [
            'amount' => [
                'value' => $amount,
                'currency' => 'RUB',
            ],
            'capture' => true,
            'confirmation' => [
                'type' => 'redirect',
                'return_url' => $returnUrl,
            ],
            'description' => 'Заказ ' . $order->number,
            'metadata' => [
                'orderId' => (int)$order->id,
                'orderNumber' => (string)$order->number,
            ],
        ];

        $receiptResult = $this->resolveReceipt($order);
        $receiptIncluded = false;
        $receiptSkipReason = $receiptResult['skipReason'];
        if ($receiptResult['receipt'] !== null) {
            $payload['receipt'] = $receiptResult['receipt'];
            $receiptIncluded = true;
            $receiptSkipReason = null;
        }

        YooKassaHttpLogger::logFlow('createRedirectPayment receipt', [
            'orderNumber' => (string)$order->number,
            'receiptIncluded' => $receiptIncluded,
            'receiptSkipReason' => $receiptSkipReason,
        ]);

        $response = $this->client->createPayment($payload, $this->idempotenceKey($order));

        $externalId = trim((string)($response['id'] ?? ''));
        $confirmationUrl = trim((string)($response['confirmation']['confirmation_url'] ?? ''));
        $status = trim((string)($response['status'] ?? ''));

        if ($externalId === '' || $confirmationUrl === '') {
            throw new \RuntimeException('YooKassa payment response is incomplete.');
        }

        $order->payment_provider = self::PROVIDER;
        $order->payment_external_id = $externalId;
        $order->payment_status = OrderPaymentMapper::PAYMENT_STATUS_PENDING;
        $order->payment_method = OrderPaymentMapper::METHOD_ONLINE;
        $order->save(false, [
            'payment_provider',
            'payment_external_id',
            'payment_status',
            'payment_method',
            'updated_at',
        ]);

        YooKassaHttpLogger::logFlow('createRedirectPayment success', [
            'orderNumber' => (string)$order->number,
            'paymentId' => $externalId,
            'status' => $status,
            'receiptIncluded' => $receiptIncluded,
            'shopId' => $this->client->getShopId(),
        ]);

        return [
            'externalId' => $externalId,
            'confirmationUrl' => $confirmationUrl,
            'returnUrl' => $returnUrl,
            'status' => $status,
            'receiptIncluded' => $receiptIncluded,
            'receiptSkipReason' => $receiptSkipReason,
        ];
    }

    public function buildReturnUrlForOrder(Order $order): string
    {
        return $this->resolveReturnUrl($order);
    }

    /**
     * Dev/test: финальный статус платежа без GET /payments (симуляция webhook).
     *
     * @param 'succeeded'|'canceled' $outcome
     */
    public function applySimulatedOutcome(Order $order, string $outcome): Order
    {
        $outcome = strtolower(trim($outcome));
        if (!in_array($outcome, ['succeeded', 'canceled'], true)) {
            throw new \InvalidArgumentException('Outcome must be succeeded or canceled.');
        }

        $paymentId = trim((string)($order->payment_external_id ?? ''));
        if ($paymentId === '') {
            $paymentId = 'test-sim-' . (int)$order->id . '-' . substr(md5((string)$order->number), 0, 8);
            $order->payment_external_id = $paymentId;
            $order->payment_provider = self::PROVIDER;
            $order->save(false, ['payment_external_id', 'payment_provider', 'updated_at']);
        }

        $payment = [
            'id' => $paymentId,
            'status' => $outcome === 'succeeded' ? 'succeeded' : 'canceled',
            'metadata' => [
                'orderId' => (int)$order->id,
                'orderNumber' => (string)$order->number,
            ],
        ];
        $event = $outcome === 'succeeded' ? 'payment.succeeded' : 'payment.canceled';

        $updated = $this->applyPaymentState($payment, $event);
        if ($updated === null) {
            throw new \RuntimeException('Could not apply simulated payment outcome.');
        }

        return $updated;
    }

    /**
     * Подтянуть статус из API ЮKassa (return_url часто раньше webhook).
     */
    public function syncOrderPaymentFromProvider(Order $order): Order
    {
        if (!$this->isConfigured()) {
            return $order;
        }
        if ($order->payment_method !== OrderPaymentMapper::METHOD_ONLINE) {
            return $order;
        }

        $paymentId = trim((string)($order->payment_external_id ?? ''));
        if ($paymentId === '') {
            return $order;
        }

        if ($order->payment_status === OrderPaymentMapper::PAYMENT_STATUS_PAID) {
            return $order;
        }

        try {
            $payment = $this->client->getPayment($paymentId);
        } catch (\Throwable $e) {
            Yii::warning(
                'YooKassa sync payment failed for order ' . $order->number . ': ' . $e->getMessage(),
                __METHOD__,
            );

            return $order;
        }

        $providerStatus = strtolower(trim((string)($payment['status'] ?? '')));
        $event = match ($providerStatus) {
            'succeeded' => 'payment.succeeded',
            'canceled' => 'payment.canceled',
            default => null,
        };

        $updated = $this->applyPaymentState($payment, $event);

        return $updated ?? $order;
    }

    /**
     * Idempotent webhook / status sync.
     *
     * @param array<string, mixed> $notification
     */
    public function handleNotification(array $notification): ?Order
    {
        $event = (string)($notification['event'] ?? '');
        $object = $notification['object'] ?? null;
        if (!is_array($object)) {
            return null;
        }

        $paymentId = trim((string)($object['id'] ?? ''));
        if ($paymentId === '') {
            return null;
        }

        // Verify against API — do not trust webhook body alone.
        YooKassaHttpLogger::logFlow('handleNotification verify', [
            'event' => $event,
            'paymentId' => $paymentId,
        ]);
        $payment = $this->client->getPayment($paymentId);

        return $this->applyPaymentState($payment, $event);
    }

    /**
     * @param array<string, mixed> $payment
     */
    public function applyPaymentState(array $payment, ?string $event = null): ?Order
    {
        $paymentId = trim((string)($payment['id'] ?? ''));
        if ($paymentId === '') {
            return null;
        }

        $order = Order::find()
            ->where(['payment_external_id' => $paymentId])
            ->one();

        if ($order === null) {
            $orderNumber = trim((string)($payment['metadata']['orderNumber'] ?? ''));
            if ($orderNumber !== '') {
                $order = Order::find()->where(['number' => $orderNumber])->one();
            }
        }

        if ($order === null) {
            Yii::warning('YooKassa payment without local order: ' . $paymentId, __METHOD__);

            return null;
        }

        $status = (string)($payment['status'] ?? '');
        if ($status === 'succeeded' || $event === 'payment.succeeded') {
            if ($order->payment_status === OrderPaymentMapper::PAYMENT_STATUS_PAID
                && $order->status !== Order::STATUS_PENDING_PAYMENT) {
                $this->clearGuestCartAfterSuccessfulPayment($order);

                return $order;
            }

            $order->payment_status = OrderPaymentMapper::PAYMENT_STATUS_PAID;
            $order->payment_provider = self::PROVIDER;
            $order->payment_external_id = $paymentId;
            $order->paid_at = date('Y-m-d H:i:s');
            if ($order->status === Order::STATUS_PENDING_PAYMENT) {
                $order->status = Order::STATUS_NEW;
            }
            $order->save(false, [
                'payment_status',
                'payment_provider',
                'payment_external_id',
                'paid_at',
                'status',
                'updated_at',
            ]);

            $this->clearGuestCartAfterSuccessfulPayment($order);

            return $order;
        }

        if ($status === 'canceled' || $event === 'payment.canceled') {
            if ($order->payment_status === OrderPaymentMapper::PAYMENT_STATUS_CANCELLED) {
                return $order;
            }

            $order->payment_status = OrderPaymentMapper::PAYMENT_STATUS_CANCELLED;
            $order->payment_provider = self::PROVIDER;
            $order->payment_external_id = $paymentId;
            if ($order->status === Order::STATUS_PENDING_PAYMENT) {
                $order->status = Order::STATUS_CANCELLED;
            }
            $order->save(false, [
                'payment_status',
                'payment_provider',
                'payment_external_id',
                'status',
                'updated_at',
            ]);

            return $order;
        }

        return $order;
    }

    private function resolveReturnUrl(Order $order): string
    {
        $base = trim((string)(Yii::$app->params['yookassa']['returnUrl'] ?? ''));
        if ($base === '') {
            $base = 'https://dev.front-p-833.tw1.ru/cart?fromPayment=1';
        }

        $separator = str_contains($base, '?') ? '&' : '?';

        return $base . $separator . 'number=' . rawurlencode((string)$order->number);
    }

    private function clearGuestCartAfterSuccessfulPayment(Order $order): void
    {
        if ($order->user_id !== null) {
            return;
        }

        $sessionId = trim((string)($order->session_id ?? ''));
        if ($sessionId === '') {
            return;
        }

        $this->cartService->clear(new ApiOwnerContext(null, $sessionId));
    }

    private function idempotenceKey(Order $order): string
    {
        return 'order-' . (int)$order->id . '-' . md5((string)$order->number . '|' . $order->total_amount);
    }

    /**
     * @return array{receipt: array<string, mixed>|null, skipReason: string|null}
     */
    private function resolveReceipt(Order $order): array
    {
        $params = Yii::$app->params['yookassa'] ?? [];
        if (($params['sendReceipt'] ?? true) === false) {
            return ['receipt' => null, 'skipReason' => 'sendReceipt disabled (YOOKASSA_SEND_RECEIPT=0)'];
        }
        if ($this->receiptCustomer($order) === null) {
            return [
                'receipt' => null,
                'skipReason' => 'no email and no phone for receipt fallback',
            ];
        }

        try {
            return ['receipt' => $this->buildReceipt($order), 'skipReason' => null];
        } catch (\Throwable $e) {
            return ['receipt' => null, 'skipReason' => 'build error: ' . $e->getMessage()];
        }
    }

    /**
     * Данные чека для «Чеков от ЮKassa» (54-ФЗ). Без контакта покупателя receipt не собираем.
     *
     * @return array<string, mixed>|null
     */
    private function buildReceipt(Order $order): ?array
    {
        $params = Yii::$app->params['yookassa'] ?? [];
        $customer = $this->receiptCustomer($order);
        if ($customer === null) {
            throw new \RuntimeException('receiptCustomer is empty.');
        }

        $vatCode = (int)($params['receiptVatCode'] ?? 1);
        $measure = trim((string)($params['receiptMeasure'] ?? 'piece'));
        if ($measure === '') {
            $measure = 'piece';
        }
        $taxSystemCode = (int)($params['receiptTaxSystemCode'] ?? 1);
        $paymentMode = trim((string)($params['receiptPaymentMode'] ?? 'full_prepayment'));
        if ($paymentMode === '') {
            $paymentMode = 'full_prepayment';
        }
        $items = [];
        $orderItems = $order->items;
        if ($orderItems === []) {
            $order->refresh();
            $orderItems = $order->items;
        }

        foreach ($orderItems as $line) {
            $lineAmount = (float)($line->paid_line_total ?? $line->line_total ?? 0);
            if ($lineAmount <= 0) {
                continue;
            }
            $qty = max(1, (int)$line->quantity);
            $unit = round($lineAmount / $qty, 2);
            $items[] = $this->receiptLineItem(
                $this->truncateReceiptText((string)$line->product_title, 128),
                (float)$qty,
                $unit,
                $vatCode,
                'commodity',
                $measure,
                $paymentMode,
            );
        }

        $surcharge = round((float)($order->cashless_surcharge_amount ?? 0), 2);
        if ($surcharge > 0) {
            $items[] = $this->receiptLineItem(
                'Наценка за безналичную оплату',
                1.0,
                $surcharge,
                $vatCode,
                'service',
                $measure,
                $paymentMode,
            );
        }

        if ($items === []) {
            $items[] = $this->receiptLineItem(
                'Заказ ' . $order->number,
                1.0,
                (float)$order->total_amount,
                $vatCode,
                'commodity',
                $measure,
                $paymentMode,
            );
        } else {
            $itemsSum = 0.0;
            foreach ($items as $row) {
                $itemsSum += (float)($row['amount']['value'] ?? 0) * (float)($row['quantity'] ?? 1);
            }
            $orderTotal = round((float)$order->total_amount, 2);
            if (abs($itemsSum - $orderTotal) > 0.01) {
                $items = [
                    $this->receiptLineItem(
                        'Заказ ' . $order->number,
                        1.0,
                        $orderTotal,
                        $vatCode,
                        'commodity',
                        $measure,
                        $paymentMode,
                    ),
                ];
            }
        }

        $receipt = [
            'customer' => $customer,
            'items' => $items,
            'tax_system_code' => $taxSystemCode,
        ];
        if (($params['receiptInternet'] ?? true) !== false) {
            $receipt['internet'] = 'true';
        }
        $timezone = (int)($params['receiptTimezone'] ?? 3);
        if ($timezone >= 1 && $timezone <= 12) {
            $receipt['timezone'] = $timezone;
        }

        return $receipt;
    }

    /**
     * @return array<string, mixed>
     */
    private function receiptLineItem(
        string $description,
        float $quantity,
        float $unitAmount,
        int $vatCode,
        string $paymentSubject,
        string $measure,
        string $paymentMode,
    ): array {
        $qty = max(0.001, $quantity);
        $unit = max(0.01, round($unitAmount, 2));

        return [
            'description' => $this->truncateReceiptText($description, 128),
            'quantity' => number_format($qty, 3, '.', ''),
            'amount' => [
                'value' => number_format($unit, 2, '.', ''),
                'currency' => 'RUB',
            ],
            'vat_code' => $vatCode,
            'payment_mode' => $paymentMode,
            'payment_subject' => $paymentSubject,
            'measure' => $measure,
        ];
    }

    /**
     * @return array<string, string>|null
     */
    private function receiptCustomer(Order $order): ?array
    {
        $params = Yii::$app->params['yookassa'] ?? [];
        $destination = trim((string)($params['receiptDestinationEmail'] ?? ''));
        if ($destination !== '' && filter_var($destination, FILTER_VALIDATE_EMAIL)) {
            $receiptEmail = $destination;
        } else {
            $email = trim((string)($order->customer_email ?? ''));
            $receiptEmail = null;
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $receiptEmail = $email;
            } else {
                $receiptEmail = $this->fallbackReceiptEmail($order);
            }
        }

        if ($receiptEmail === null) {
            return null;
        }

        // Чеки от ЮKassa доставляются на email (не SMS): https://yookassa.ru/developers/payment-acceptance/receipts/54fz/yoomoney/basics
        $customer = ['email' => $receiptEmail];
        $phone = $this->formatReceiptPhoneDigits((string)($order->customer_phone ?? ''));
        if ($phone !== null) {
            $customer['phone'] = $phone;
        }

        return $customer;
    }

    /**
     * Технический email для чека, если в форме заказа почты нет (поле необязательно в UI).
     */
    private function fallbackReceiptEmail(Order $order): ?string
    {
        $params = Yii::$app->params['yookassa'] ?? [];
        $domain = trim((string)($params['receiptFallbackEmailDomain'] ?? 'noreply.dev.front-p-833.tw1.ru'));
        if ($domain === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', (string)($order->customer_phone ?? '')) ?? '';
        if (strlen($digits) === 11 && str_starts_with($digits, '8')) {
            $digits = '7' . substr($digits, 1);
        }
        if (strlen($digits) === 10) {
            $digits = '7' . $digits;
        }
        if (!preg_match('/^7\d{10}$/', $digits)) {
            return null;
        }

        $candidate = 'guest+' . $digits . '@' . $domain;
        if (!filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return $candidate;
    }

    /**
     * Телефон для receipt.customer: 11 цифр, 7XXXXXXXXXX (формат в доке ЮKassa).
     */
    private function formatReceiptPhoneDigits(string $phone): ?string
    {
        $phone = trim($phone);
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (strlen($digits) === 11 && str_starts_with($digits, '8')) {
            $digits = '7' . substr($digits, 1);
        }
        if (strlen($digits) === 10) {
            $digits = '7' . $digits;
        }
        if (preg_match('/^7\d{10}$/', $digits) === 1) {
            return $digits;
        }

        return null;
    }

    private function truncateReceiptText(string $text, int $maxLength): string
    {
        if ($maxLength <= 0) {
            return '';
        }
        if (function_exists('mb_substr')) {
            return mb_substr($text, 0, $maxLength);
        }

        return strlen($text) <= $maxLength ? $text : substr($text, 0, $maxLength);
    }
}
