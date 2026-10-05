<?php

namespace app\models;

use app\services\order\OrderItemPricingSnapshot;
use app\services\order\OrderLinePricingHelper;
use yii\db\ActiveRecord;

class OrderItem extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%order_items}}';
    }

    public function rules(): array
    {
        return [
            [['order_id', 'product_title', 'quantity', 'unit_price', 'line_total'], 'required'],
            [['order_id', 'quantity'], 'integer', 'min' => 1],
            [['product_title'], 'string', 'max' => 255],
            [['product_sku'], 'string', 'max' => 64],
            [['unit_price', 'retail_unit_price', 'dealer_unit_price', 'line_total', 'promo_discount_amount', 'cashback_used_amount', 'paid_line_total', 'catalog_promotion_discount'], 'number', 'min' => 0],
            [['has_catalog_promotion', 'is_custom'], 'boolean'],
            [['dealer_discount_percent'], 'integer', 'min' => 0, 'max' => 100],
            [['catalog_promotion_snapshot'], 'string'],
            [['has_catalog_promotion'], 'default', 'value' => false],
            [['catalog_promotion_discount'], 'default', 'value' => 0],
            [['is_custom'], 'default', 'value' => false],
            [['comment'], 'string', 'max' => 2000],
            [['attachment_path'], 'string', 'max' => 512],
            [['attachment_original_name'], 'string', 'max' => 255],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'product_title' => 'Товар',
            'product_sku' => 'Артикул',
            'quantity' => 'Кол-во',
            'unit_price' => 'Цена',
            'line_total' => 'Сумма',
            'promo_discount_amount' => 'Скидка промо',
            'cashback_used_amount' => 'Кэшбек',
            'paid_line_total' => 'К оплате',
        ];
    }

    public function getOrder()
    {
        return $this->hasOne(Order::class, ['id' => 'order_id']);
    }

    public function beforeValidate(): bool
    {
        if ($this->quantity > 0 && $this->unit_price !== null && $this->unit_price !== '') {
            $this->line_total = round((float)$this->quantity * (float)$this->unit_price, 2);
        }

        if ($this->paid_line_total === null || $this->paid_line_total === '') {
            $promo = round((float)($this->promo_discount_amount ?? 0), 2);
            $cashback = round((float)($this->cashback_used_amount ?? 0), 2);
            $this->paid_line_total = round(max(0, (float)$this->line_total - $promo - $cashback), 2);
        }

        return parent::beforeValidate();
    }

    /**
     * @return array<string, mixed>
     */
    public function toApiItem(): array
    {
        $retailPrice = OrderLinePricingHelper::retailUnitPrice($this);
        $retailLineTotal = OrderLinePricingHelper::retailLineTotal($this);
        $dealerPrice = OrderLinePricingHelper::dealerUnitPrice($this);

        return array_merge([
            'productId' => $this->product_sku,
            'title' => $this->product_title,
            'quantity' => (int)$this->quantity,
            'retailPrice' => $retailPrice,
            'retailLineTotal' => $retailLineTotal,
            'dealerPrice' => $dealerPrice !== null ? (int)round($dealerPrice) : null,
            'unitPrice' => (float)$this->unit_price,
            'lineTotal' => (float)$this->line_total,
            'promoDiscountAmount' => (float)$this->promo_discount_amount,
            'cashbackUsedAmount' => (float)$this->cashback_used_amount,
            'paidLineTotal' => (float)$this->paid_line_total,
            'custom' => (bool)$this->is_custom,
            'comment' => $this->comment,
            'attachment' => $this->buildAttachmentPayload(),
        ], OrderItemPricingSnapshot::toOrderLineApiFields($this));
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
