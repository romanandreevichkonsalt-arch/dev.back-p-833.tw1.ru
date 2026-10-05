<?php

namespace app\services\order;

use app\models\DealerPromoGrant;
use app\services\dealer\DealerPromoService;

/**
 * Распределяет скидку промо и кэшбек по позициям заказа (snapshot на момент оформления).
 */
class OrderLinePricingAllocator
{
    public function __construct(
        private readonly DealerPromoService $promoService = new DealerPromoService(),
    ) {
    }

    /**
     * @param list<array{lineTotal: float, catalogModelId: int|null, hasCatalogPromotion?: bool}> $lines
     * @return list<array{promoDiscountAmount: float, cashbackUsedAmount: float, paidLineTotal: float}>
     */
    public function allocate(
        array $lines,
        float $subtotal,
        float $totalPromoDiscount,
        float $totalCashbackUsed,
        ?DealerPromoGrant $promoGrant,
    ): array {
        $count = count($lines);
        if ($count === 0) {
            return [];
        }

        $promoByIndex = array_fill(0, $count, 0.0);
        $cashbackByIndex = array_fill(0, $count, 0.0);

        if ($totalPromoDiscount > 0 && $promoGrant !== null) {
            $promoByIndex = $this->allocateProportional(
                $lines,
                $totalPromoDiscount,
                $this->buildPromoEligibleMask($lines, $promoGrant),
            );
        } elseif ($totalCashbackUsed > 0 && $subtotal > 0) {
            $cashbackByIndex = $this->allocateProportional(
                $lines,
                $totalCashbackUsed,
                $this->buildCashbackEligibleMask($lines),
            );
        }

        $result = [];
        foreach ($lines as $index => $line) {
            $lineTotal = round((float)$line['lineTotal'], 2);
            $promo = round($promoByIndex[$index], 2);
            $cashback = round($cashbackByIndex[$index], 2);
            $paid = round(max(0, $lineTotal - $promo - $cashback), 2);

            $result[] = [
                'promoDiscountAmount' => $promo,
                'cashbackUsedAmount' => $cashback,
                'paidLineTotal' => $paid,
            ];
        }

        return $result;
    }

    /**
     * @param list<array{lineTotal: float, catalogModelId: int|null, hasCatalogPromotion?: bool}> $lines
     * @return list<bool>
     */
    /**
     * @param list<array{lineTotal: float, catalogModelId: int|null, hasCatalogPromotion?: bool}> $lines
     * @return list<bool>
     */
    private function buildCashbackEligibleMask(array $lines): array
    {
        $mask = [];
        foreach ($lines as $line) {
            if (!empty($line['hasCatalogPromotion'])) {
                $mask[] = false;
                continue;
            }
            $mask[] = (float)($line['lineTotal'] ?? 0) > 0;
        }

        return $mask;
    }

    private function buildPromoEligibleMask(array $lines, DealerPromoGrant $grant): array
    {
        $scopeModelId = $this->promoService->resolveModelScopeForGrant($grant);
        $mask = [];

        foreach ($lines as $line) {
            $lineTotal = (float)($line['lineTotal'] ?? 0);
            if ($lineTotal <= 0 || !empty($line['hasCatalogPromotion'])) {
                $mask[] = false;
                continue;
            }

            if ($scopeModelId === null) {
                $mask[] = true;
                continue;
            }

            $itemModelId = $line['catalogModelId'] ?? null;
            $mask[] = $itemModelId !== null && (int)$itemModelId === $scopeModelId;
        }

        return $mask;
    }

    /**
     * @param list<array{lineTotal: float, catalogModelId: int|null, hasCatalogPromotion?: bool}> $lines
     * @param list<bool> $eligibleMask
     * @return list<float>
     */
    private function allocateProportional(array $lines, float $totalAmount, array $eligibleMask): array
    {
        $count = count($lines);
        $amounts = array_fill(0, $count, 0.0);
        if ($totalAmount <= 0) {
            return $amounts;
        }

        $eligibleTotal = 0.0;
        $eligibleIndexes = [];
        foreach ($lines as $index => $line) {
            if (!($eligibleMask[$index] ?? false)) {
                continue;
            }
            $lineTotal = (float)($line['lineTotal'] ?? 0);
            if ($lineTotal <= 0) {
                continue;
            }
            $eligibleTotal += $lineTotal;
            $eligibleIndexes[] = $index;
        }

        if ($eligibleTotal <= 0 || $eligibleIndexes === []) {
            return $amounts;
        }

        $allocated = 0.0;
        $lastIndex = $eligibleIndexes[array_key_last($eligibleIndexes)];
        foreach ($eligibleIndexes as $index) {
            if ($index === $lastIndex) {
                $amounts[$index] = round($totalAmount - $allocated, 2);
                break;
            }

            $lineTotal = (float)$lines[$index]['lineTotal'];
            $share = round($totalAmount * $lineTotal / $eligibleTotal, 2);
            $amounts[$index] = $share;
            $allocated += $share;
        }

        return $amounts;
    }
}
