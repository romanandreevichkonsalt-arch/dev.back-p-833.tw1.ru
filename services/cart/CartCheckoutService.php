<?php

namespace app\services\cart;

use app\exceptions\ApiValidationException;
use app\models\CartItem;
use app\models\DealerCartCheckout;
use app\models\DealerPromoGrant;
use app\models\User;
use app\services\dealer\CashbackService;
use app\services\dealer\DealerActivityLogger;
use app\services\dealer\DealerPricingService;
use app\services\dealer\DealerPromoService;
use Yii;

class CartCheckoutService
{
    public function __construct(
        private readonly DealerPromoService $promoService = new DealerPromoService(),
        private readonly CashbackService $cashbackService = new CashbackService(),
        private readonly DealerActivityLogger $activityLogger = new DealerActivityLogger(),
    ) {
    }

    public function getOrCreateCheckout(int $userId): DealerCartCheckout
    {
        $checkout = DealerCartCheckout::findOne($userId);
        if ($checkout === null) {
            $checkout = new DealerCartCheckout([
                'user_id' => $userId,
                'cashback_amount' => 0,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $checkout->save(false);
        }

        return $checkout;
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return array<string, mixed>
     */
    public function enrichPayload(int $userId, array $items, int $totalQuantity, ?User $user = null): array
    {
        $subtotal = 0.0;
        $retailSubtotal = 0.0;
        $catalogPromotionDiscount = 0.0;
        foreach ($items as $item) {
            $subtotal += (float)($item['lineTotal'] ?? 0);
            $retailSubtotal += (float)($item['retailLineTotal'] ?? $item['lineTotal'] ?? 0);
            $catalogPromotionDiscount += (float)($item['catalogPromotionDiscount'] ?? 0);
        }
        $subtotal = round($subtotal, 2);
        $retailSubtotal = round($retailSubtotal, 2);
        $catalogPromotionDiscount = round($catalogPromotionDiscount, 2);
        $dealerDiscountAmount = round(max(0, $retailSubtotal - $subtotal), 2);

        $checkout = $this->getOrCreateCheckout($userId);
        $canUseDealerBonuses = $this->canUseDealerBonuses($user);
        if (!$canUseDealerBonuses) {
            $this->clearDealerBonusesFromCheckout($checkout);
        }

        $promoGrant = $canUseDealerBonuses && $checkout->promo_grant_id !== null
            ? $this->resolveOwnedPromoGrant($checkout, $userId)
            : null;

        $promoDiscount = 0.0;
        if ($promoGrant !== null && $promoGrant->isUsable()) {
            $promoDiscount = $this->calculatePromoDiscount($promoGrant, $items);
        } elseif ($checkout->promo_grant_id !== null) {
            $this->clearPromoFromCheckout($checkout);
        }

        $cashbackAvailable = 0.0;
        if ($canUseDealerBonuses) {
            $cashbackAvailable = (float)$this->cashbackService->getWidget($userId)['balance'];
        }

        $cashbackEligibleSubtotal = $this->resolveCashbackEligibleSubtotal($items);

        $cashbackApplied = 0.0;
        $cashbackMaxApplicable = 0.0;
        if ($canUseDealerBonuses && $promoGrant === null) {
            $cashbackMaxApplicable = $this->resolveCashbackMaxApplicable(
                $cashbackAvailable,
                $cashbackEligibleSubtotal,
                $promoDiscount,
            );
            if ((float)$checkout->cashback_amount > 0) {
                $cashbackApplied = $this->resolveCashbackUsedAmount(
                    (float)$checkout->cashback_amount,
                    $cashbackAvailable,
                    $cashbackEligibleSubtotal,
                    $promoDiscount,
                );
            }
        } elseif (!$canUseDealerBonuses && (float)$checkout->cashback_amount > 0) {
            $this->clearDealerBonusesFromCheckout($checkout);
        }

        $total = round(max(0, $subtotal - $promoDiscount - $cashbackApplied), 2);

        return [
            'items' => $items,
            'totalQuantity' => $totalQuantity,
            'retailSubtotal' => $retailSubtotal,
            'dealerDiscountAmount' => $dealerDiscountAmount,
            'subtotal' => $subtotal,
            'promoDiscount' => $promoDiscount,
            'cashbackApplied' => $cashbackApplied,
            'total' => $total,
            'promo' => $promoGrant !== null && $promoGrant->isUsable() ? [
                'code' => $promoGrant->code,
                'title' => $promoGrant->getTitle(),
                'discountPercent' => (float)$promoGrant->discount_percent,
            ] : null,
            'cashbackAvailable' => $cashbackAvailable,
            'cashbackMaxApplicable' => $cashbackMaxApplicable,
            'catalogPromotionDiscount' => $catalogPromotionDiscount,
            'discounts' => $this->buildCartDiscountsPayload(
                $dealerDiscountAmount,
                $promoDiscount,
                $cashbackApplied,
                $promoGrant !== null && $promoGrant->isUsable() ? $promoGrant : null,
                $catalogPromotionDiscount,
            ),
        ];
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return array<string, mixed>
     */
    public function enrichGuestPayload(array $items, int $totalQuantity): array
    {
        $subtotal = 0.0;
        $retailSubtotal = 0.0;
        foreach ($items as $item) {
            $subtotal += (float)($item['lineTotal'] ?? 0);
            $retailSubtotal += (float)($item['retailLineTotal'] ?? $item['lineTotal'] ?? 0);
        }
        $subtotal = round($subtotal, 2);
        $retailSubtotal = round($retailSubtotal, 2);

        return [
            'items' => $items,
            'totalQuantity' => $totalQuantity,
            'retailSubtotal' => $retailSubtotal,
            'dealerDiscountAmount' => round(max(0, $retailSubtotal - $subtotal), 2),
            'subtotal' => $subtotal,
            'promoDiscount' => 0.0,
            'cashbackApplied' => 0.0,
            'total' => $subtotal,
            'promo' => null,
            'cashbackAvailable' => 0.0,
            'cashbackMaxApplicable' => 0.0,
            'catalogPromotionDiscount' => 0.0,
            'discounts' => $this->buildCartDiscountsPayload(0.0, 0.0, 0.0, null, 0.0),
        ];
    }

    public function applyPromo(User $user, string $code): array
    {
        if (!$user->isDealer()) {
            throw new ApiValidationException('Промокод доступен только дилерам.');
        }

        $grant = $this->promoService->findUsableGrant((int)$user->id, $code);
        if ($grant === null) {
            throw new ApiValidationException('Промокод недоступен или уже использован.');
        }

        $checkout = $this->getOrCreateCheckout((int)$user->id);
        $checkout->promo_grant_id = (int)$grant->id;
        $checkout->cashback_amount = 0;
        $checkout->updated_at = date('Y-m-d H:i:s');
        $checkout->save(false);

        $this->activityLogger->log($user, 'promo.apply', ['code' => $grant->code]);

        return ['ok' => true, 'code' => $grant->code];
    }

    public function clearPromo(int $userId): void
    {
        $checkout = $this->getOrCreateCheckout($userId);
        $checkout->promo_grant_id = null;
        $checkout->updated_at = date('Y-m-d H:i:s');
        $checkout->save(false);
    }

    public function applyCashback(User $user, ?float $amount = null): array
    {
        if (!$user->isDealer()) {
            throw new ApiValidationException('Кэшбек доступен только дилерам.');
        }

        $checkout = $this->getOrCreateCheckout((int)$user->id);
        if ($checkout->promo_grant_id !== null) {
            throw new ApiValidationException('Кэшбек нельзя использовать вместе с промокодом.');
        }

        $available = (float)$this->cashbackService->getWidget((int)$user->id)['balance'];
        $useAmount = $amount ?? $available;
        $useAmount = round(min(max(0, $useAmount), $available), 2);

        $checkout->cashback_amount = $useAmount;
        $checkout->updated_at = date('Y-m-d H:i:s');
        $checkout->save(false);

        $this->activityLogger->log($user, 'cashback.apply', ['amount' => $useAmount]);

        return ['ok' => true, 'amount' => $useAmount];
    }

    public function clearCashback(int $userId): void
    {
        $checkout = $this->getOrCreateCheckout($userId);
        $checkout->cashback_amount = 0;
        $checkout->updated_at = date('Y-m-d H:i:s');
        $checkout->save(false);
    }

    /**
     * @param list<array{lineTotal: float, catalogModelId: int|null}> $lines
     * @return array{subtotal:float,promoDiscount:float,cashbackUsed:float,total:float,promoGrant:?DealerPromoGrant}
     */
    public function getCheckoutTotalsForLines(\app\services\guest\ApiOwnerContext $owner, array $lines): array
    {
        $subtotal = round(array_sum(array_map(
            static fn (array $line): float => (float)($line['lineTotal'] ?? 0),
            $lines,
        )), 2);

        if ($owner->userId === null) {
            return [
                'subtotal' => $subtotal,
                'promoDiscount' => 0.0,
                'cashbackUsed' => 0.0,
                'total' => $subtotal,
                'promoGrant' => null,
            ];
        }

        $user = $owner->identity();
        $userId = (int)$owner->userId;
        $canUseDealerBonuses = $this->canUseDealerBonuses($user);

        $checkout = $this->getOrCreateCheckout($userId);
        if (!$canUseDealerBonuses) {
            $this->clearDealerBonusesFromCheckout($checkout);
        }

        $promoGrant = $canUseDealerBonuses && $checkout->promo_grant_id !== null
            ? $this->resolveOwnedPromoGrant($checkout, $userId)
            : null;

        $promoDiscount = $canUseDealerBonuses
            ? $this->calculatePromoDiscount($promoGrant, $lines)
            : 0.0;

        $cashbackUsed = $this->resolveCheckoutCashbackUsed(
            $userId,
            $checkout,
            $lines,
            $promoDiscount,
            $promoGrant,
            $canUseDealerBonuses,
        );
        if (!$canUseDealerBonuses && (float)$checkout->cashback_amount > 0) {
            $this->clearDealerBonusesFromCheckout($checkout);
        }

        return [
            'subtotal' => $subtotal,
            'promoDiscount' => $promoDiscount,
            'cashbackUsed' => $cashbackUsed,
            'total' => round(max(0, $subtotal - $promoDiscount - $cashbackUsed), 2),
            'promoGrant' => $canUseDealerBonuses && $promoGrant !== null && $promoGrant->isUsable() ? $promoGrant : null,
        ];
    }

    /**
     * @return array{subtotal:float,promoDiscount:float,cashbackUsed:float,total:float,promoGrant:?DealerPromoGrant}
     */
    public function getCheckoutTotalsForOwner(\app\services\guest\ApiOwnerContext $owner): array
    {
        $items = CartItem::find()->where($this->ownerWhere($owner))->with(['product'])->all();
        $dealer = ($owner->identity() !== null && $owner->identity()->isDealer()) ? $owner->identity() : null;
        $cartService = new CartService();
        $subtotal = 0.0;
        foreach ($items as $item) {
            if ($item->product === null) {
                continue;
            }
            $line = $cartService->buildCartLineForProduct($item->product, (int)$item->quantity, $dealer);
            $subtotal += (float)($line['lineTotal'] ?? 0);
        }
        $subtotal = round($subtotal, 2);

        if ($owner->userId === null) {
            return [
                'subtotal' => $subtotal,
                'promoDiscount' => 0.0,
                'cashbackUsed' => 0.0,
                'total' => $subtotal,
                'promoGrant' => null,
            ];
        }

        return $this->getCheckoutTotals((int)$owner->userId, $owner->identity());
    }

    /**
     * @return array{subtotal:float,promoDiscount:float,cashbackUsed:float,total:float,promoGrant:?DealerPromoGrant}
     */
    public function getCheckoutTotals(int $userId, ?User $user = null): array
    {
        $user ??= User::findOne($userId);
        $canUseDealerBonuses = $this->canUseDealerBonuses($user);

        $items = CartItem::find()->where(['user_id' => $userId])->with(['product'])->all();

        $checkout = $this->getOrCreateCheckout($userId);
        if (!$canUseDealerBonuses) {
            $this->clearDealerBonusesFromCheckout($checkout);
        }

        $promoGrant = $canUseDealerBonuses && $checkout->promo_grant_id !== null
            ? $this->resolveOwnedPromoGrant($checkout, $userId)
            : null;

        $dealer = ($user !== null && $user->isDealer()) ? $user : null;
        $cartService = new CartService();
        $lines = [];
        foreach ($items as $item) {
            if ($item->product === null) {
                continue;
            }
            $lines[] = $cartService->buildCartLineForProduct($item->product, (int)$item->quantity, $dealer);
        }

        $subtotal = round(array_sum(array_map(
            static fn (array $line): float => (float)($line['lineTotal'] ?? 0),
            $lines,
        )), 2);

        $promoDiscount = $canUseDealerBonuses
            ? $this->calculatePromoDiscount($promoGrant, $lines)
            : 0.0;

        $cashbackUsed = $this->resolveCheckoutCashbackUsed(
            $userId,
            $checkout,
            $lines,
            $promoDiscount,
            $promoGrant,
            $canUseDealerBonuses,
        );
        if (!$canUseDealerBonuses && (float)$checkout->cashback_amount > 0) {
            $this->clearDealerBonusesFromCheckout($checkout);
        }

        return [
            'subtotal' => $subtotal,
            'promoDiscount' => $promoDiscount,
            'cashbackUsed' => $cashbackUsed,
            'total' => round(max(0, $subtotal - $promoDiscount - $cashbackUsed), 2),
            'promoGrant' => $canUseDealerBonuses && $promoGrant !== null && $promoGrant->isUsable() ? $promoGrant : null,
        ];
    }

    /**
     * @param list<array<string, mixed>> $lines
     */
    private function resolveCheckoutCashbackUsed(
        int $userId,
        DealerCartCheckout $checkout,
        array $lines,
        float $promoDiscount,
        ?DealerPromoGrant $promoGrant,
        bool $canUseDealerBonuses,
    ): float {
        if (!$canUseDealerBonuses || $promoGrant !== null || (float)$checkout->cashback_amount <= 0) {
            return 0.0;
        }

        $cashbackAvailable = (float)$this->cashbackService->getWidget($userId)['balance'];
        $eligibleSubtotal = $this->resolveCashbackEligibleSubtotal($lines);

        return $this->resolveCashbackUsedAmount(
            (float)$checkout->cashback_amount,
            $cashbackAvailable,
            $eligibleSubtotal,
            $promoDiscount,
        );
    }

    /**
     * @param list<array<string, mixed>> $items
     */
    private function resolveCashbackEligibleSubtotal(array $items): float
    {
        $total = 0.0;
        foreach ($items as $item) {
            if (!empty($item['hasCatalogPromotion'])) {
                continue;
            }
            $total += (float)($item['lineTotal'] ?? 0);
        }

        return round($total, 2);
    }

    private function resolveCashbackUsedAmount(
        float $requestedAmount,
        float $availableBalance,
        float $subtotal,
        float $promoDiscount,
    ): float {
        return round(min(
            $requestedAmount,
            $this->resolveCashbackMaxApplicable($availableBalance, $subtotal, $promoDiscount),
        ), 2);
    }

    private function resolveCashbackMaxApplicable(
        float $availableBalance,
        float $subtotal,
        float $promoDiscount,
    ): float {
        $payable = max(0, $subtotal - $promoDiscount);
        $percent = (float)(Yii::$app->params['cashback']['maxOrderSpendPercent'] ?? 50);
        if ($percent <= 0) {
            return 0.0;
        }
        $maxByPercent = round($payable * min(100, $percent) / 100, 2);

        return round(min($availableBalance, $payable, $maxByPercent), 2);
    }

    private function canUseDealerBonuses(?User $user): bool
    {
        return $user !== null && $user->isDealer() && !$user->is_blocked;
    }

    private function clearDealerBonusesFromCheckout(DealerCartCheckout $checkout): void
    {
        if ($checkout->promo_grant_id === null && (float)$checkout->cashback_amount <= 0) {
            return;
        }

        $checkout->promo_grant_id = null;
        $checkout->cashback_amount = 0;
        $checkout->updated_at = date('Y-m-d H:i:s');
        $checkout->save(false);
    }

    private function clearPromoFromCheckout(DealerCartCheckout $checkout): void
    {
        if ($checkout->promo_grant_id === null) {
            return;
        }

        $checkout->promo_grant_id = null;
        $checkout->updated_at = date('Y-m-d H:i:s');
        $checkout->save(false);
    }

    private function resolveOwnedPromoGrant(DealerCartCheckout $checkout, int $userId): ?DealerPromoGrant
    {
        if ($checkout->promo_grant_id === null) {
            return null;
        }

        $grant = DealerPromoGrant::findOne((int)$checkout->promo_grant_id);
        if ($grant === null || (int)$grant->user_id !== $userId) {
            $this->clearPromoFromCheckout($checkout);

            return null;
        }

        return $grant;
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
     * @param list<array{lineTotal?: float|int|string, catalogModelId?: int|null}> $items
     */
    private function calculatePromoDiscount(?DealerPromoGrant $grant, array $items): float
    {
        if ($grant === null || !$grant->isUsable()) {
            return 0.0;
        }

        $scopeModelId = $this->promoService->resolveModelScopeForGrant($grant);
        $eligibleSubtotal = 0.0;

        foreach ($items as $item) {
            $lineTotal = (float)($item['lineTotal'] ?? 0);
            if ($lineTotal <= 0) {
                continue;
            }

            if (!empty($item['hasCatalogPromotion'])) {
                continue;
            }

            if ($scopeModelId === null) {
                $eligibleSubtotal += $lineTotal;
                continue;
            }

            $itemModelId = $item['catalogModelId'] ?? null;
            if ($itemModelId !== null && (int)$itemModelId === $scopeModelId) {
                $eligibleSubtotal += $lineTotal;
            }
        }

        if ($eligibleSubtotal <= 0) {
            return 0.0;
        }

        return round($eligibleSubtotal * (float)$grant->discount_percent / 100, 2);
    }

    public function resetCheckout(int $userId): void
    {
        DealerCartCheckout::deleteAll(['user_id' => $userId]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCartDiscountsPayload(
        float $dealerDiscountAmount,
        float $promoDiscount,
        float $cashbackApplied,
        ?DealerPromoGrant $promoGrant,
        float $catalogPromotionDiscount = 0.0,
    ): array {
        $payload = [
            'dealer' => [
                'amount' => round($dealerDiscountAmount, 2),
            ],
            'promotion' => null,
            'promo' => null,
            'cashback' => null,
        ];

        if ($catalogPromotionDiscount > 0) {
            $payload['promotion'] = [
                'amount' => round($catalogPromotionDiscount, 2),
            ];
        }

        if ($promoDiscount > 0) {
            $payload['promo'] = [
                'amount' => round($promoDiscount, 2),
                'code' => $promoGrant?->code,
            ];
        }

        if ($cashbackApplied > 0) {
            $payload['cashback'] = [
                'amount' => round($cashbackApplied, 2),
            ];
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function ownerWhere(\app\services\guest\ApiOwnerContext $owner): array
    {
        if ($owner->userId !== null) {
            return ['user_id' => $owner->userId];
        }

        return ['session_id' => $owner->sessionId];
    }
}
