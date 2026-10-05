<?php

namespace app\services\order;

use app\exceptions\ApiValidationException;
use app\models\CatalogProduct;
use app\models\Order;
use app\models\OrderItem;
use app\models\OrderDocument;
use app\models\User;
use app\services\cart\CartCheckoutService;
use app\services\cart\CartService;
use app\services\cart\CartAttachmentUploadService;
use app\services\dealer\DealerPricingService;
use app\services\order\OrderItemPricingSnapshot;
use app\services\dealer\CashbackService;
use app\services\dealer\CustomerUserFactory;
use app\services\dealer\DealerActivityLogger;
use app\services\dealer\DealerPromoService;
use app\services\guest\ApiOwnerContext;
use app\services\payment\YooKassaPaymentService;
use Yii;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\UploadedFile;

class OrderApiService
{
    public function __construct(
        private readonly CartService $cartService = new CartService(),
        private readonly CartCheckoutService $checkoutService = new CartCheckoutService(),
        private readonly CustomerUserFactory $customerUserFactory = new CustomerUserFactory(),
        private readonly DealerActivityLogger $activityLogger = new DealerActivityLogger(),
        private readonly DealerPromoService $promoService = new DealerPromoService(),
        private readonly CashbackService $cashbackService = new CashbackService(),
        private readonly OrderAttachmentUploadService $attachmentUploadService = new OrderAttachmentUploadService(),
        private readonly CartAttachmentUploadService $cartAttachmentUploadService = new CartAttachmentUploadService(),
        private readonly OrderItemApiEnricher $itemEnricher = new OrderItemApiEnricher(),
        private readonly OrderLinePricingAllocator $linePricingAllocator = new OrderLinePricingAllocator(),
        private readonly OrderManagerNotificationMailer $managerNotificationMailer = new OrderManagerNotificationMailer(),
        private readonly YooKassaPaymentService $yooKassaPaymentService = new YooKassaPaymentService(),
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listOrders(int $userId): array
    {
        $orders = Order::find()
            ->where(['user_id' => $userId])
            ->with(['items'])
            ->orderBy(['id' => SORT_DESC])
            ->all();

        $allItems = [];
        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                $allItems[] = $item;
            }
        }
        $productsBySlug = $this->itemEnricher->loadProductsForItems($allItems);
        $options = ['productsBySlug' => $productsBySlug, 'enricher' => $this->itemEnricher];

        return array_map(fn (Order $order): array => $order->toApiSummary($options), $orders);
    }

    /**
     * @return array<string, mixed>
     */
    public function getOrder(ApiOwnerContext $owner, string $number): array
    {
        $order = $this->findOrderForOwner($owner, $number);
        if ($order === null) {
            throw new NotFoundHttpException('Заказ не найден.');
        }

        $order = $this->yooKassaPaymentService->syncOrderPaymentFromProvider($order);

        return $order->toApiPayload($this->buildApiOptions($order));
    }

    /**
     * Публичный статус оплаты после return_url ЮKassa (без X-Session-ID).
     *
     * @return array<string, mixed>
     */
    public function getGuestPaymentStatusPublic(string $number): array
    {
        $order = Order::find()
            ->where(['number' => $number, 'user_id' => null])
            ->one();

        if ($order === null || $order->payment_method !== OrderPaymentMapper::METHOD_ONLINE) {
            throw new NotFoundHttpException('Заказ не найден.');
        }

        $order = $this->yooKassaPaymentService->syncOrderPaymentFromProvider($order);

        return [
            'number' => (string)$order->number,
            'status' => (string)$order->status,
            'statusLabel' => $order->getStatusLabel(),
            'paymentStatus' => $order->payment_status,
            'paymentStatusLabel' => OrderPaymentMapper::paymentStatusLabel($order->payment_status),
            'paymentReturnTarget' => OrderPaymentMapper::paymentReturnTarget($order->payment_status),
            'paidAt' => $order->paid_at,
            'paymentExternalId' => trim((string)($order->payment_external_id ?? '')) ?: null,
            'paymentCartReturnUrl' => $this->yooKassaPaymentService->buildReturnUrlForOrder($order),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, UploadedFile> $itemAttachments ключ — productId (slug)
     * @return array<string, mixed>
     */
    public function createFromCart(ApiOwnerContext $owner, array $payload, array $itemAttachments = []): array
    {
        $isGuest = $owner->isGuest();
        $userId = $owner->userId;
        $dealer = $userId !== null ? User::findOne($userId) : null;
        $isDealer = $dealer !== null && $dealer->isDealer();

        $atomicItems = $this->parseAtomicItemsFromPayload($payload);
        $itemCommentOverrides = $isDealer && $atomicItems === null
            ? $this->parseItemCommentsFromPayload($payload)
            : [];

        if ($isGuest) {
            $this->assertGuestCheckoutPayload($payload, $itemAttachments, $atomicItems);
        } else {
            $this->assertAuthenticatedCheckoutPayload(
                $payload,
                $itemAttachments,
                $itemCommentOverrides,
                $isDealer,
                $atomicItems,
            );
        }

        $usedCart = false;
        if ($atomicItems !== null) {
            $lineSources = $this->buildLineSourcesFromPayload($atomicItems, $isDealer, $dealer);
        } else {
            $cartItems = $this->cartService->getItemsForOwner($owner);
            if ($cartItems === []) {
                throw new ApiValidationException('Укажите позиции заказа.', [
                    'items' => ['Передайте items[{productId, quantity}] или добавьте товары в корзину.'],
                ]);
            }
            $lineSources = $this->buildLineSourcesFromCart($cartItems, $itemCommentOverrides, $isDealer, $dealer);
            $usedCart = true;
        }

        $orderSlugs = array_map(
            static fn (array $line): string => (string)$line['slug'],
            $lineSources,
        );

        foreach (array_keys($itemAttachments) as $attachmentSlug) {
            if (!in_array($attachmentSlug, $orderSlugs, true)) {
                throw new ApiValidationException('Файл не относится к позиции заказа.', [
                    'itemAttachments[' . $attachmentSlug . ']' => ['Позиции с таким productId нет в заказе.'],
                ]);
            }
        }

        $checkoutLines = array_map(
            static fn (array $line): array => [
                'lineTotal' => $line['lineTotal'],
                'catalogModelId' => $line['catalogModelId'],
                'hasCatalogPromotion' => (bool)($line['hasCatalogPromotion'] ?? false),
            ],
            $lineSources,
        );
        $checkoutTotals = $atomicItems !== null
            ? $this->checkoutService->getCheckoutTotalsForLines($owner, $checkoutLines)
            : $this->checkoutService->getCheckoutTotalsForOwner($owner);

        $customerName = trim((string)($payload['customerName'] ?? ''));
        $customerPhone = trim((string)($payload['customerPhone'] ?? ''));
        $customerEmail = $this->resolveCustomerEmailFromPayload($payload);

        $customerUser = $this->customerUserFactory->findOrCreateFromOrderData(
            $customerName,
            $customerPhone,
            $customerEmail,
        );

        $paymentMethod = $isGuest
            ? OrderPaymentMapper::METHOD_ONLINE
            : $this->parsePaymentMethod($payload);
        $cashlessSurchargeAmount = OrderPaymentMapper::cashlessSurchargeAmount(
            $paymentMethod,
            $checkoutTotals['subtotal'],
        );
        $orderTotalAmount = round($checkoutTotals['total'] + $cashlessSurchargeAmount, 2);
        $isOnlineGuestCheckout = $isGuest && $orderTotalAmount > 0;

        $order = new Order([
            'number' => Order::generateNumber(),
            'user_id' => $userId,
            'session_id' => $isGuest ? $owner->sessionId : null,
            'customer_user_id' => $customerUser !== null ? (int)$customerUser->id : null,
            'customer_name' => $customerName,
            'customer_phone' => $customerPhone,
            'customer_email' => $customerEmail,
            'delivery_address' => trim((string)($payload['deliveryAddress'] ?? '')) ?: null,
            'comment' => null,
            'payment_method' => $paymentMethod,
            'payment_status' => $isOnlineGuestCheckout
                ? OrderPaymentMapper::PAYMENT_STATUS_PENDING
                : null,
            'payment_provider' => $isOnlineGuestCheckout ? YooKassaPaymentService::PROVIDER : null,
            'cashless_surcharge_amount' => $cashlessSurchargeAmount,
            'status' => $isOnlineGuestCheckout ? Order::STATUS_PENDING_PAYMENT : Order::STATUS_NEW,
            'subtotal_amount' => $checkoutTotals['subtotal'],
            'promo_discount_amount' => $checkoutTotals['promoDiscount'],
            'cashback_used_amount' => $checkoutTotals['cashbackUsed'],
            'total_amount' => $orderTotalAmount,
            'promo_grant_id' => $checkoutTotals['promoGrant']?->id,
        ]);

        if (!$order->validate()) {
            throw new ApiValidationException('Ошибка валидации.', $order->getErrors());
        }

        /** @var list<string> $savedAttachmentPaths */
        $savedAttachmentPaths = [];

        $pricingByLine = $this->linePricingAllocator->allocate(
            $checkoutLines,
            $checkoutTotals['subtotal'],
            $checkoutTotals['promoDiscount'],
            $checkoutTotals['cashbackUsed'],
            $checkoutTotals['promoGrant'],
        );

        $transaction = Yii::$app->db->beginTransaction();
        try {
            $order->save(false);

            foreach ($lineSources as $index => $lineSource) {
                $pricing = $pricingByLine[$index] ?? [
                    'promoDiscountAmount' => 0.0,
                    'cashbackUsedAmount' => 0.0,
                    'paidLineTotal' => $lineSource['lineTotal'],
                ];

                $item = new OrderItem([
                    'order_id' => (int)$order->id,
                    'product_title' => $lineSource['title'],
                    'product_sku' => $lineSource['slug'],
                    'is_custom' => $lineSource['isCustom'],
                    'quantity' => $lineSource['quantity'],
                    'unit_price' => $lineSource['unitPrice'],
                    'retail_unit_price' => $lineSource['retailUnitPrice'] ?? $lineSource['unitPrice'],
                    'dealer_unit_price' => $lineSource['dealerUnitPrice'] ?? null,
                    'has_catalog_promotion' => (bool)($lineSource['hasCatalogPromotion'] ?? false),
                    'catalog_promotion_discount' => (float)($lineSource['catalogPromotionDiscount'] ?? 0),
                    'catalog_promotion_snapshot' => OrderItemPricingSnapshot::encodeCatalogPromotion(
                        is_array($lineSource['catalogPromotion'] ?? null) ? $lineSource['catalogPromotion'] : null,
                    ),
                    'dealer_discount_percent' => isset($lineSource['dealerDiscountPercent'])
                        ? (int)$lineSource['dealerDiscountPercent']
                        : null,
                    'promo_discount_amount' => $pricing['promoDiscountAmount'],
                    'cashback_used_amount' => $pricing['cashbackUsedAmount'],
                    'paid_line_total' => $pricing['paidLineTotal'],
                    'comment' => $lineSource['comment'],
                ]);
                $item->beforeValidate();
                $item->save(false);

                $slug = $lineSource['slug'];
                $attachment = $itemAttachments[$slug] ?? null;
                if ($attachment !== null && $isDealer) {
                    $saved = $this->attachmentUploadService->saveForOrderItem($order, $item, $attachment);
                    $savedAttachmentPaths[] = $saved['path'];
                    $item->attachment_path = $saved['path'];
                    $item->attachment_original_name = $saved['originalName'];
                    $item->save(false, ['attachment_path', 'attachment_original_name']);
                } elseif ($isDealer && $lineSource['cartItem'] !== null) {
                    $cartItem = $lineSource['cartItem'];
                    if ($cartItem->hasAttribute('attachment_path')
                        && $cartItem->attachment_path !== null
                        && trim((string)$cartItem->attachment_path) !== '') {
                        $saved = $this->cartAttachmentUploadService->transferToOrderItem($cartItem, $order, $item);
                        $savedAttachmentPaths[] = $saved['path'];
                        $item->attachment_path = $saved['path'];
                        $item->attachment_original_name = $saved['originalName'];
                        $item->save(false, ['attachment_path', 'attachment_original_name']);
                        $cartItem->attachment_path = null;
                        $cartItem->attachment_original_name = null;
                        $cartItem->save(false, ['attachment_path', 'attachment_original_name']);
                    }
                }
            }

            $order->refresh();
            if ($checkoutTotals['total'] <= 0 && $checkoutTotals['subtotal'] > 0) {
                $order->recalculateTotal();
            }
            $order->save(false, ['total_amount', 'subtotal_amount', 'updated_at']);

            if ($checkoutTotals['promoGrant'] !== null) {
                $this->promoService->markUsed($checkoutTotals['promoGrant'], (int)$order->id);
            }
            if ($checkoutTotals['cashbackUsed'] > 0 && $dealer !== null) {
                $this->cashbackService->spend((int)$dealer->id, $checkoutTotals['cashbackUsed'], (int)$order->id);
            }

            $clearCartAfterCommit = $usedCart && !($isOnlineGuestCheckout && $isGuest);
            if ($clearCartAfterCommit) {
                $this->cartService->clear($owner);
            }
            if ($userId !== null) {
                $this->checkoutService->resetCheckout($userId);
            }
            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            foreach ($savedAttachmentPaths as $path) {
                $this->attachmentUploadService->deleteStoredFile($path);
            }
            throw $e;
        }

        if ($isDealer) {
            $activityContext = ['number' => $order->number];
            if ($checkoutTotals['promoGrant'] !== null) {
                $activityContext['promoCode'] = $checkoutTotals['promoGrant']->code;
            }
            $this->activityLogger->log($dealer, 'order.create', $activityContext);
            $this->activityLogger->promoteDealerTypeIfNeeded($dealer);
        }

        $savedOrder = Order::find()
            ->where(['id' => $order->id])
            ->with(['items', 'promoGrant', 'documents'])
            ->one();

        if ($isDealer && $savedOrder !== null) {
            $this->managerNotificationMailer->notifyDealerOrderCreated($savedOrder, $dealer);
        }

        $confirmationUrl = null;
        $paymentReturnUrl = null;
        $paymentReceiptIncluded = null;
        if ($isOnlineGuestCheckout && $savedOrder !== null) {
            try {
                $payment = $this->yooKassaPaymentService->createRedirectPayment($savedOrder);
                $confirmationUrl = $payment['confirmationUrl'];
                $paymentReturnUrl = $payment['returnUrl'] ?? null;
                $paymentReceiptIncluded = (bool)($payment['receiptIncluded'] ?? false);
                $savedOrder->refresh();
            } catch (\Throwable $e) {
                Yii::error('YooKassa create payment failed: ' . $e->getMessage(), __METHOD__);
                $savedOrder->payment_status = OrderPaymentMapper::PAYMENT_STATUS_FAILED;
                $savedOrder->save(false, ['payment_status', 'updated_at']);
                throw new ApiValidationException('Не удалось создать платёж. Попробуйте позже.', [
                    'payment' => [$e->getMessage()],
                ]);
            }
        }

        $payloadResponse = $savedOrder !== null
            ? $savedOrder->toApiPayload($this->buildApiOptions($savedOrder))
            : $order->toApiPayload($this->buildApiOptions($order));

        if ($confirmationUrl !== null) {
            $payloadResponse['paymentConfirmationUrl'] = $confirmationUrl;
        }
        if ($paymentReturnUrl !== null) {
            $payloadResponse['paymentReturnUrl'] = $paymentReturnUrl;
        }
        if ($isOnlineGuestCheckout && $savedOrder !== null) {
            $externalId = trim((string)($savedOrder->payment_external_id ?? ''));
            if ($externalId !== '') {
                $payloadResponse['paymentExternalId'] = $externalId;
            }
            if ($paymentReceiptIncluded !== null) {
                $payloadResponse['paymentReceiptIncluded'] = $paymentReceiptIncluded;
            }
        }

        return $payloadResponse;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildApiOptions(Order $order): array
    {
        $items = $order->items;
        $productsBySlug = $this->itemEnricher->loadProductsForItems($items);

        return [
            'productsBySlug' => $productsBySlug,
            'enricher' => $this->itemEnricher,
        ];
    }

    public function findOrderItemAttachment(ApiOwnerContext $owner, string $number, string $productId): OrderItem
    {
        $order = $this->findOrderForOwner($owner, $number);
        if ($order === null) {
            throw new NotFoundHttpException('Заказ не найден.');
        }

        $productId = trim($productId);
        foreach ($order->items as $item) {
            if ((string)$item->product_sku === $productId) {
                if ($item->attachment_path === null || trim($item->attachment_path) === '') {
                    throw new NotFoundHttpException('Файл не найден.');
                }

                return $item;
            }
        }

        throw new NotFoundHttpException('Позиция заказа не найдена.');
    }

    public function findOrderDocument(ApiOwnerContext $owner, string $number, int $documentId): OrderDocument
    {
        $order = $this->findOrderForOwner($owner, $number);
        if ($order === null) {
            throw new NotFoundHttpException('Заказ не найден.');
        }

        $document = OrderDocument::findOne(['id' => $documentId, 'order_id' => (int)$order->id]);
        if ($document === null) {
            throw new NotFoundHttpException('Документ не найден.');
        }

        return $document;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function parsePaymentMethod(array $payload): ?string
    {
        $method = OrderPaymentMapper::normalizeMethod($payload['paymentMethod'] ?? null);
        if ($method !== null) {
            return $method;
        }

        return OrderPaymentMapper::normalizeMethod($payload['payment_method'] ?? null);
    }

    /**
     * @param array<string, UploadedFile> $itemAttachments
     * @param array<string, string> $itemCommentOverrides
     * @param array<string, mixed> $payload
     */
    private function assertAuthenticatedCheckoutPayload(
        array $payload,
        array $itemAttachments,
        array $itemCommentOverrides,
        bool $isDealer,
        ?array $atomicItems,
    ): void {
        if (trim((string)($payload['comment'] ?? '')) !== '') {
            throw new ApiValidationException('Комментарий к заказу не поддерживается.', [
                'comment' => ['Укажите комментарий к позиции в items[].comment.'],
            ]);
        }

        if (!$isDealer) {
            if ($itemAttachments !== [] || $itemCommentOverrides !== []) {
                throw new ForbiddenHttpException('Комментарии и файлы доступны только дилерам.');
            }
            if ($atomicItems !== null) {
                foreach ($atomicItems as $item) {
                    if (($item['comment'] ?? null) !== null && trim((string)$item['comment']) !== '') {
                        throw new ForbiddenHttpException('Комментарии к позициям доступны только дилерам.');
                    }
                }
            }
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, UploadedFile> $itemAttachments
     * @param list<array{productId: string, quantity: int, comment: ?string}>|null $atomicItems
     */
    private function assertGuestCheckoutPayload(array $payload, array $itemAttachments, ?array $atomicItems): void
    {
        if ($itemAttachments !== []) {
            throw new ForbiddenHttpException('Гостям нельзя прикреплять файлы к заказу.');
        }

        if (trim((string)($payload['comment'] ?? '')) !== '') {
            throw new ForbiddenHttpException('Гостям нельзя оставлять комментарий к заказу.');
        }

        if ($atomicItems === null && $this->parseItemCommentsFromPayload($payload) !== []) {
            throw new ForbiddenHttpException('Гостям нельзя оставлять комментарии к позициям.');
        }

        if ($atomicItems !== null) {
            foreach ($atomicItems as $item) {
                if (($item['comment'] ?? null) !== null && trim((string)$item['comment']) !== '') {
                    throw new ForbiddenHttpException('Гостям нельзя оставлять комментарии к позициям.');
                }
            }
        }
    }

    /**
     * @param list<array{productId: string, quantity: int, comment: ?string}> $atomicItems
     * @return list<array{
     *     slug: string,
     *     title: string,
     *     quantity: int,
     *     unitPrice: float,
     *     lineTotal: float,
     *     catalogModelId: int|null,
     *     isCustom: bool,
     *     comment: ?string,
     *     cartItem: null
     * }>
     */
    private function buildLineSourcesFromPayload(array $atomicItems, bool $isDealer, ?User $dealer): array
    {
        $lines = [];
        $dealerUser = $isDealer ? $dealer : null;
        foreach ($atomicItems as $index => $item) {
            $product = CatalogProduct::find()
                ->where(['slug' => $item['productId'], 'is_active' => true])
                ->one();
            if ($product === null) {
                throw new ApiValidationException('Товар не найден.', [
                    'items[' . $index . '].productId' => ['SKU «' . $item['productId'] . '» не найден или неактивен.'],
                ]);
            }

            $quantity = (int)$item['quantity'];
            $cartLine = $this->cartService->buildCartLineForProduct($product, $quantity, $dealerUser);

            $lines[] = $this->mapCartLineToLineSource(
                $cartLine,
                (string)$product->slug,
                (string)$product->title,
                $quantity,
                (bool)$product->is_custom,
                $isDealer ? ($item['comment'] ?: null) : null,
                null,
            );
        }

        return $lines;
    }

    /**
     * @param list<\app\models\CartItem> $cartItems
     * @param array<string, string> $itemCommentOverrides
     * @return list<array<string, mixed>>
     */
    private function buildLineSourcesFromCart(array $cartItems, array $itemCommentOverrides, bool $isDealer, ?User $dealer): array
    {
        $lines = [];
        $dealerUser = $isDealer ? $dealer : null;
        foreach ($cartItems as $cartItem) {
            $product = $cartItem->product;
            if ($product === null) {
                continue;
            }

            $slug = (string)$product->slug;
            $quantity = (int)$cartItem->quantity;
            $cartLine = $this->cartService->buildLineFromCartItem($cartItem, $dealerUser);

            $lines[] = $this->mapCartLineToLineSource(
                $cartLine,
                $slug,
                (string)$product->title,
                $quantity,
                (bool)$product->is_custom,
                $isDealer
                    ? ($itemCommentOverrides[$slug] ?? ($cartItem->comment ?: null))
                    : null,
                $cartItem,
            );
        }

        return $lines;
    }

    /**
     * @param array<string, mixed> $cartLine
     * @return array<string, mixed>
     */
    private function mapCartLineToLineSource(
        array $cartLine,
        string $slug,
        string $title,
        int $quantity,
        bool $isCustom,
        ?string $comment,
        ?\app\models\CartItem $cartItem,
    ): array {
        $retailUnit = isset($cartLine['retailPrice']) ? (float)$cartLine['retailPrice'] : null;
        $dealerUnit = isset($cartLine['dealerPrice']) ? (float)$cartLine['dealerPrice'] : null;

        return [
            'slug' => $slug,
            'title' => $title,
            'quantity' => $quantity,
            'unitPrice' => (float)$cartLine['unitPrice'],
            'retailUnitPrice' => $retailUnit ?? (float)$cartLine['unitPrice'],
            'dealerUnitPrice' => $dealerUnit,
            'lineTotal' => (float)$cartLine['lineTotal'],
            'catalogModelId' => $cartLine['catalogModelId'] ?? null,
            'isCustom' => $isCustom,
            'comment' => $comment,
            'cartItem' => $cartItem,
            'hasCatalogPromotion' => (bool)($cartLine['hasCatalogPromotion'] ?? false),
            'catalogPromotionDiscount' => (float)($cartLine['catalogPromotionDiscount'] ?? 0),
            'catalogPromotion' => is_array($cartLine['catalogPromotion'] ?? null)
                ? $cartLine['catalogPromotion']
                : null,
            'dealerDiscountPercent' => isset($cartLine['dealerDiscountPercent'])
                ? (int)$cartLine['dealerDiscountPercent']
                : null,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return list<array{productId: string, quantity: int, comment: ?string}>|null
     */
    private function parseAtomicItemsFromPayload(array $payload): ?array
    {
        $items = $payload['items'] ?? null;
        if (is_string($items)) {
            $decoded = json_decode($items, true);
            $items = is_array($decoded) ? $decoded : null;
        }
        if (!is_array($items) || $items === []) {
            return null;
        }

        $hasQuantity = false;
        $hasWithoutQuantity = false;
        $parsed = [];
        $seen = [];

        foreach ($items as $index => $item) {
            if (!is_array($item)) {
                throw new ApiValidationException('Ошибка валидации.', [
                    'items[' . $index . ']' => ['Элемент items должен быть объектом.'],
                ]);
            }

            $productId = trim((string)($item['productId'] ?? ''));
            if ($productId === '') {
                throw new ApiValidationException('Ошибка валидации.', [
                    'items[' . $index . '].productId' => ['Поле productId обязательно.'],
                ]);
            }

            if (array_key_exists('quantity', $item)) {
                $hasQuantity = true;
                $quantity = (int)$item['quantity'];
                if ($quantity < 1) {
                    throw new ApiValidationException('Ошибка валидации.', [
                        'items[' . $index . '].quantity' => ['Количество должно быть не меньше 1.'],
                    ]);
                }
                if (isset($seen[$productId])) {
                    throw new ApiValidationException('Ошибка валидации.', [
                        'items[' . $index . '].productId' => ['Дублирующийся productId «' . $productId . '».'],
                    ]);
                }
                $seen[$productId] = true;

                $comment = trim((string)($item['comment'] ?? ''));
                $parsed[] = [
                    'productId' => $productId,
                    'quantity' => $quantity,
                    'comment' => $comment !== '' ? $comment : null,
                ];
            } else {
                $hasWithoutQuantity = true;
            }
        }

        if ($hasQuantity && $hasWithoutQuantity) {
            throw new ApiValidationException('Ошибка валидации.', [
                'items' => ['Либо все позиции с quantity (создание заказа одним запросом), либо только comment для корзины.'],
            ]);
        }

        return $hasQuantity ? $parsed : null;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, string> productId (slug) => comment
     */
    private function parseItemCommentsFromPayload(array $payload): array
    {
        $items = $payload['items'] ?? [];
        if (!is_array($items)) {
            return [];
        }

        $result = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $productId = trim((string)($item['productId'] ?? ''));
            if ($productId === '') {
                continue;
            }

            $comment = trim((string)($item['comment'] ?? ''));
            if ($comment !== '') {
                $result[$productId] = $comment;
            }
        }

        return $result;
    }

    private function findOrderForOwner(ApiOwnerContext $owner, string $number): ?Order
    {
        if ($owner->userId !== null) {
            return Order::find()
                ->where(['user_id' => $owner->userId, 'number' => $number])
                ->with(['items', 'promoGrant', 'documents'])
                ->one();
        }

        return Order::find()
            ->where(['session_id' => $owner->sessionId, 'number' => $number])
            ->with(['items', 'promoGrant', 'documents'])
            ->one();
    }

    /**
     * @return array{unitPrice: float, retailUnitPrice: float, dealerUnitPrice: ?float}
     */
    private function resolveLinePricing(CatalogProduct $product, ?User $dealer): array
    {
        $pricingService = new DealerPricingService();
        $prices = $pricingService->buildProductPrices($product, $dealer);
        $retail = $prices['retailPrice'] ?? null;
        $retailUnitPrice = $retail !== null ? (float)$retail : $this->parsePriceDisplay($product->price_display);
        $dealerUnitPrice = isset($prices['dealerPrice']) ? (float)$prices['dealerPrice'] : null;
        $unitPrice = $dealerUnitPrice ?? $retailUnitPrice;

        return [
            'unitPrice' => $unitPrice,
            'retailUnitPrice' => $retailUnitPrice,
            'dealerUnitPrice' => $dealerUnitPrice,
        ];
    }

    private function parsePriceDisplay(?string $display): float
    {
        if ($display === null || trim($display) === '') {
            return 0.0;
        }

        $digits = preg_replace('/[^\d]/', '', $display);

        return $digits !== '' ? (float)$digits : 0.0;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function resolveCustomerEmailFromPayload(array $payload): ?string
    {
        $email = trim((string)($payload['customerEmail'] ?? $payload['customer_email'] ?? ''));

        return $email !== '' ? $email : null;
    }
}
