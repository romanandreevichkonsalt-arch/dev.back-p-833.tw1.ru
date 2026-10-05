<?php

namespace app\models;

use app\services\dealer\CashbackService;
use app\services\order\OrderItemApiEnricher;
use app\services\order\OrderLinePricingHelper;
use app\services\order\OrderPaymentMapper;
use app\services\order\OrderUiMapper;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

class Order extends ActiveRecord
{
    public const STATUS_PENDING_PAYMENT = 'pending_payment';
    public const STATUS_NEW = 'new';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_PRODUCTION = 'production';
    public const STATUS_READY = 'ready';
    public const STATUS_SHIPPING = 'shipping';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public static function tableName(): string
    {
        return '{{%orders}}';
    }

    public function behaviors(): array
    {
        return [
            'timestamp' => [
                'class' => TimestampBehavior::class,
                'value' => static fn (): string => date('Y-m-d H:i:s'),
            ],
        ];
    }

    public function rules(): array
    {
        return [
            [['number', 'customer_name', 'customer_phone', 'status'], 'required'],
            [['number'], 'string', 'max' => 32],
            [['number'], 'unique'],
            [['customer_name'], 'string', 'max' => 255],
            [['customer_phone'], 'match', 'pattern' => '/^\+7\d{10}$/'],
            [['customer_email'], 'email'],
            [['customer_email'], 'string', 'max' => 255],
            [['delivery_address'], 'string', 'max' => 512],
            [['comment', 'manager_comment'], 'string'],
            [['status'], 'in', 'range' => array_keys(self::statusLabels())],
            [['payment_method'], 'in', 'range' => array_keys(\app\services\order\OrderPaymentMapper::methodLabels())],
            [['payment_method'], 'default', 'value' => null],
            [['cashless_surcharge_amount'], 'number', 'min' => 0],
            [['total_amount'], 'number', 'min' => 0],
            [['subtotal_amount', 'promo_discount_amount', 'cashback_used_amount'], 'number', 'min' => 0],
            [['payment_status', 'payment_provider'], 'string', 'max' => 32],
            [['payment_external_id'], 'string', 'max' => 64],
            [['paid_at'], 'safe'],
            [['user_id', 'assigned_to', 'customer_user_id', 'promo_grant_id'], 'integer'],
            [['session_id'], 'string', 'max' => 64],
            [['attachment_path'], 'string', 'max' => 512],
            [['attachment_original_name'], 'string', 'max' => 255],
            [['created_at', 'updated_at'], 'safe'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'number' => 'Номер заказа',
            'customer_name' => 'Клиент',
            'customer_phone' => 'Телефон',
            'customer_email' => 'Email',
            'delivery_address' => 'Адрес доставки',
            'comment' => 'Комментарий клиента',
            'payment_method' => 'Способ оплаты',
            'cashless_surcharge_amount' => 'Наценка безнал',
            'status' => 'Статус',
            'total_amount' => 'Сумма',
            'manager_comment' => 'Комментарий менеджера',
            'assigned_to' => 'Ответственный',
            'created_at' => 'Создан',
            'updated_at' => 'Обновлён',
        ];
    }

    public function getItems()
    {
        return $this->hasMany(OrderItem::class, ['order_id' => 'id']);
    }

    public function getStatusLogs()
    {
        return $this->hasMany(OrderStatusLog::class, ['order_id' => 'id'])->orderBy(['id' => SORT_DESC]);
    }

    public function getAssignee()
    {
        return $this->hasOne(AdminUser::class, ['id' => 'assigned_to']);
    }

    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public function getCustomerUser()
    {
        return $this->hasOne(User::class, ['id' => 'customer_user_id']);
    }

    public function getPromoGrant()
    {
        return $this->hasOne(DealerPromoGrant::class, ['id' => 'promo_grant_id']);
    }

    public function getDocuments()
    {
        return $this->hasMany(OrderDocument::class, ['order_id' => 'id'])
            ->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC]);
    }

    public static function statusLabels(): array
    {
        return [
            self::STATUS_PENDING_PAYMENT => 'Ожидает оплаты',
            self::STATUS_NEW => 'Новый',
            self::STATUS_CONFIRMED => 'Подтверждён',
            self::STATUS_PRODUCTION => 'В производстве',
            self::STATUS_READY => 'Готов',
            self::STATUS_SHIPPING => 'Доставляется',
            self::STATUS_COMPLETED => 'Выполнен',
            self::STATUS_CANCELLED => 'Отменён',
        ];
    }

    public function getStatusLabel(): string
    {
        return self::statusLabels()[$this->status] ?? $this->status;
    }

    public function isOpen(): bool
    {
        return !in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_CANCELLED], true);
    }

    public function isPaymentPaid(): bool
    {
        if (!$this->hasAttribute('payment_status')) {
            return false;
        }

        return $this->payment_status === OrderPaymentMapper::PAYMENT_STATUS_PAID;
    }

    public function afterSave($insert, $changedAttributes): void
    {
        parent::afterSave($insert, $changedAttributes);

        $becamePaid = $this->isPaymentPaid()
            && (
                $insert
                || array_key_exists('payment_status', $changedAttributes)
            );

        if ($becamePaid) {
            (new CashbackService())->recordPaidOrderForPeriod($this);
        }
    }

    public static function generateNumber(): string
    {
        return 'ORD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
    }

    public function recalculateTotal(): void
    {
        $total = 0;
        foreach ($this->items as $item) {
            $total += (float)$item->line_total;
        }
        $this->total_amount = round($total, 2);
    }

    public function getFormattedTotal(): string
    {
        return number_format((float)$this->total_amount, 0, '.', ' ') . ' ₽';
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function toApiSummary(array $options = []): array
    {
        /** @var array<string, CatalogProduct> $productsBySlug */
        $productsBySlug = $options['productsBySlug'] ?? [];
        /** @var OrderItemApiEnricher|null $enricher */
        $enricher = $options['enricher'] ?? null;

        $items = $this->items;
        $itemsCount = 0;
        foreach ($items as $item) {
            $itemsCount += (int)$item->quantity;
        }

        $summaryImages = ['images' => [], 'extraCount' => 0];
        if ($enricher !== null && $items !== []) {
            $summaryImages = $enricher->buildSummaryImages($items, $productsBySlug);
        }

        return [
            'number' => $this->number,
            'status' => $this->status,
            'statusLabel' => $this->getStatusLabel(),
            'uiStatus' => OrderUiMapper::uiStatus((string)$this->status),
            'progressStep' => OrderUiMapper::progressStep((string)$this->status),
            'totalAmount' => (float)$this->total_amount,
            'itemsCount' => $itemsCount,
            'images' => $summaryImages['images'],
            'extraCount' => $summaryImages['extraCount'],
            'createdAt' => $this->created_at,
        ];
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function toApiPayload(array $options = []): array
    {
        /** @var array<string, CatalogProduct> $productsBySlug */
        $productsBySlug = $options['productsBySlug'] ?? [];
        /** @var OrderItemApiEnricher|null $enricher */
        $enricher = $options['enricher'] ?? null;

        $items = [];
        foreach ($this->items as $item) {
            $items[] = $enricher !== null
                ? $enricher->enrich($item, $productsBySlug, $this->number)
                : $item->toApiItem();
        }

        $orderItems = $this->items;
        $retailSubtotalAmount = OrderLinePricingHelper::retailSubtotal($orderItems);
        $dealerDiscountAmount = OrderLinePricingHelper::dealerDiscountTotal($orderItems);
        $catalogPromotionDiscountAmount = OrderLinePricingHelper::catalogPromotionDiscountTotal($orderItems);
        $dealerPersonalDiscountAmount = OrderLinePricingHelper::dealerPersonalDiscountTotal($orderItems);

        return [
            'number' => $this->number,
            'status' => $this->status,
            'statusLabel' => $this->getStatusLabel(),
            'uiStatus' => OrderUiMapper::uiStatus((string)$this->status),
            'progressStep' => OrderUiMapper::progressStep((string)$this->status),
            'customerName' => $this->customer_name,
            'customerPhone' => $this->customer_phone,
            'customerEmail' => $this->customer_email,
            'deliveryAddress' => $this->delivery_address,
            'comment' => $this->comment,
            'attachment' => $this->buildAttachmentPayload(),
            'retailSubtotalAmount' => $retailSubtotalAmount,
            'dealerDiscountAmount' => $dealerDiscountAmount,
            'dealerPersonalDiscountAmount' => $dealerPersonalDiscountAmount,
            'catalogPromotionDiscountAmount' => $catalogPromotionDiscountAmount,
            'subtotalAmount' => (float)$this->subtotal_amount,
            'promoDiscountAmount' => (float)$this->promo_discount_amount,
            'cashbackUsedAmount' => (float)$this->cashback_used_amount,
            'promo' => $this->buildPromoPayload(),
            'paymentMethod' => $this->payment_method,
            'paymentLabel' => \app\services\order\OrderPaymentMapper::paymentLabel($this->payment_method),
            'paymentStatus' => $this->payment_status,
            'paymentProvider' => $this->payment_provider,
            'paidAt' => $this->paid_at,
            'cashlessSurchargeAmount' => (float)$this->cashless_surcharge_amount,
            'documents' => $this->buildDocumentsPayload(),
            'totalAmount' => (float)$this->total_amount,
            'discounts' => $this->buildDiscountsPayload(
                $dealerDiscountAmount,
                $catalogPromotionDiscountAmount,
                $dealerPersonalDiscountAmount,
            ),
            'items' => $items,
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDiscountsPayload(
        float $dealerDiscountAmount,
        float $catalogPromotionDiscountAmount,
        float $dealerPersonalDiscountAmount,
    ): array {
        $promo = null;
        if ((float)$this->promo_discount_amount > 0) {
            $promo = [
                'amount' => (float)$this->promo_discount_amount,
                'code' => $this->promoGrant?->code,
            ];
        }

        $cashback = null;
        if ((float)$this->cashback_used_amount > 0) {
            $cashback = [
                'amount' => (float)$this->cashback_used_amount,
            ];
        }

        $promotion = null;
        if ($catalogPromotionDiscountAmount > 0) {
            $promotion = [
                'amount' => round($catalogPromotionDiscountAmount, 2),
            ];
        }

        return [
            'dealer' => [
                'amount' => round($dealerDiscountAmount, 2),
                'personalAmount' => round($dealerPersonalDiscountAmount, 2),
            ],
            'promotion' => $promotion,
            'promo' => $promo,
            'cashback' => $cashback,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildPromoPayload(): ?array
    {
        if ($this->promo_grant_id === null && (float)$this->promo_discount_amount <= 0) {
            return null;
        }

        $grant = $this->promoGrant;
        $payload = [
            'discountAmount' => (float)$this->promo_discount_amount,
            'used' => $grant !== null && $grant->used_at !== null,
        ];

        if ($grant !== null) {
            $payload['code'] = $grant->code;
            $payload['title'] = $grant->getTitle();
            $payload['usedAt'] = $grant->used_at;
            $payload['usedOrderId'] = $grant->used_order_id;
        }

        return $payload;
    }

    /**
     * @return list<array{id: int, label: string, url: string}>
     */
    private function buildDocumentsPayload(): array
    {
        $uploadService = new \app\services\order\OrderDocumentUploadService();
        $documents = [];
        foreach ($this->documents as $document) {
            $documents[] = [
                'id' => (int)$document->id,
                'label' => $document->label,
                'url' => $uploadService->buildDownloadUrl($this->number, (int)$document->id),
            ];
        }

        return $documents;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildAttachmentPayload(): ?array
    {
        if ($this->attachment_path === null || trim($this->attachment_path) === '') {
            return null;
        }

        return [
            'originalName' => $this->attachment_original_name,
            'hasFile' => true,
        ];
    }
}
