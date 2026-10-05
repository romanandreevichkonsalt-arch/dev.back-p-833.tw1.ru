<?php

namespace app\services\order;

use app\models\DealerProfile;
use app\models\Order;
use app\models\User;
use app\services\dealer\DealerAssignedManagerService;
use app\services\order\OrderItemPricingSnapshot;
use app\services\order\OrderLinePricingHelper;
use Yii;

class OrderManagerNotificationMailer
{
    public function __construct(
        private readonly DealerAssignedManagerService $assignedManagerService = new DealerAssignedManagerService(),
    ) {
    }

    public function notifyDealerOrderCreated(Order $order, User $dealer): bool
    {
        $recipientEmail = $this->assignedManagerService->resolveNotificationEmailForUser($dealer);
        if ($recipientEmail === null) {
            Yii::warning('Dealer order notification skipped: no recipient email.', __METHOD__);

            return false;
        }

        $profile = $dealer->dealerProfile;
        $viewData = $this->buildViewData($order, $dealer, $profile);

        try {
            return (bool)Yii::$app->mailer->compose(
                ['html' => 'order/dealer-order-html', 'text' => 'order/dealer-order-text'],
                $viewData,
            )
                ->setTo($recipientEmail)
                ->setFrom([Yii::$app->params['senderEmail'] => Yii::$app->params['senderName']])
                ->setSubject('Новый заказ дилера ' . $order->number . ' — МФ Анна')
                ->send();
        } catch (\Throwable $e) {
            Yii::error('Dealer order notification failed: ' . $e->getMessage(), __METHOD__);

            return false;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildViewData(Order $order, User $dealer, ?DealerProfile $profile): array
    {
        $retailSubtotal = OrderLinePricingHelper::retailSubtotal($order->items);
        $dealerPersonalTotal = OrderLinePricingHelper::dealerPersonalDiscountTotal($order->items);
        $catalogPromotionTotal = OrderLinePricingHelper::catalogPromotionDiscountTotal($order->items);

        $items = [];
        foreach ($order->items as $item) {
            $pricing = OrderItemPricingSnapshot::toOrderLineApiFields($item);
            $promotion = is_array($pricing['catalogPromotion'] ?? null) ? $pricing['catalogPromotion'] : null;
            $pricingNote = null;
            if (!empty($pricing['hasCatalogPromotion']) && $promotion !== null) {
                $pricingNote = 'Акция: ' . trim((string)($promotion['title'] ?? $promotion['badgeText'] ?? 'каталог'));
            } elseif (isset($pricing['dealerDiscountPercent'])) {
                $pricingNote = 'Скидка дилера ' . (int)$pricing['dealerDiscountPercent'] . '%';
            }

            $line = [
                'title' => (string)$item->product_title,
                'quantity' => (int)$item->quantity,
                'retailLineTotal' => number_format(OrderLinePricingHelper::retailLineTotal($item), 0, '.', ' '),
                'lineTotal' => number_format((float)$item->line_total, 0, '.', ' '),
                'paidLineTotal' => number_format((float)$item->paid_line_total, 0, '.', ' '),
                'pricingNote' => $pricingNote,
            ];
            if ($item->comment !== null && trim((string)$item->comment) !== '') {
                $line['comment'] = trim((string)$item->comment);
            }
            if ($item->attachment_path !== null && trim((string)$item->attachment_path) !== '') {
                $line['hasAttachment'] = true;
            }
            $items[] = $line;
        }

        $promoGrant = $order->promoGrant;
        $promoCode = $promoGrant !== null ? (string)$promoGrant->code : null;

        return [
            'orderNumber' => (string)$order->number,
            'adminOrderUrl' => $this->buildAdminOrderUrl($order),
            'dealerCompanyName' => $profile !== null ? (string)$profile->company_name : '',
            'dealerInn' => $profile !== null ? ($profile->inn ?? '') : '',
            'dealerUsername' => (string)$dealer->username,
            'customerName' => (string)$order->customer_name,
            'customerPhone' => (string)$order->customer_phone,
            'customerEmail' => $order->customer_email,
            'deliveryAddress' => $order->delivery_address,
            'paymentLabel' => OrderPaymentMapper::paymentLabel($order->payment_method) ?? '—',
            'retailSubtotalAmount' => $retailSubtotal > 0
                ? number_format($retailSubtotal, 0, '.', ' ')
                : null,
            'dealerPersonalDiscountAmount' => $dealerPersonalTotal > 0
                ? number_format($dealerPersonalTotal, 0, '.', ' ')
                : null,
            'catalogPromotionDiscountAmount' => $catalogPromotionTotal > 0
                ? number_format($catalogPromotionTotal, 0, '.', ' ')
                : null,
            'subtotalAmount' => number_format((float)$order->subtotal_amount, 0, '.', ' '),
            'promoDiscountAmount' => (float)$order->promo_discount_amount > 0
                ? number_format((float)$order->promo_discount_amount, 0, '.', ' ')
                : null,
            'promoCode' => $promoCode,
            'cashbackUsedAmount' => (float)$order->cashback_used_amount > 0
                ? number_format((float)$order->cashback_used_amount, 0, '.', ' ')
                : null,
            'cashlessSurchargeAmount' => (float)$order->cashless_surcharge_amount > 0
                ? number_format((float)$order->cashless_surcharge_amount, 0, '.', ' ')
                : null,
            'totalAmount' => number_format((float)$order->total_amount, 0, '.', ' '),
            'items' => $items,
            'createdAt' => $order->created_at,
        ];
    }

    private function buildAdminOrderUrl(Order $order): ?string
    {
        $base = trim((string)(Yii::$app->params['siteBaseUrl'] ?? ''));
        if ($base === '') {
            $cabinetUrl = (string)(Yii::$app->params['dealerCabinetUrl'] ?? '');
            if ($cabinetUrl !== '' && preg_match('#^(https?://[^/]+)#', $cabinetUrl, $matches)) {
                $base = $matches[1];
            }
        }

        if ($base === '') {
            return null;
        }

        return rtrim($base, '/') . '/admin/order/view?id=' . (int)$order->id;
    }
}
