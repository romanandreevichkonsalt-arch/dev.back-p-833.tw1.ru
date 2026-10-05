<?php

namespace app\services\dealer;

class CashbackTierCalculator
{
    private const TIER_1 = 1_000_000;
    private const TIER_2 = 2_000_000;
    private const TIER_3 = 3_000_000;

    public function getPercentForTotal(float $total): float
    {
        if ($total < self::TIER_1) {
            return 0.0;
        }
        if ($total < self::TIER_2) {
            return 1.0;
        }
        if ($total < self::TIER_3) {
            return 1.5;
        }

        return 2.0;
    }

    /**
     * @return array{
     *   percent: float,
     *   nextThreshold: ?float,
     *   nextPercent: ?float,
     *   progress: float
     * }
     */
    public function getProgress(float $periodTotal): array
    {
        $percent = $this->getPercentForTotal($periodTotal);
        $thresholds = [
            ['min' => 0, 'max' => self::TIER_1, 'percent' => 0.0, 'next' => self::TIER_1, 'nextPercent' => 1.0],
            ['min' => self::TIER_1, 'max' => self::TIER_2, 'percent' => 1.0, 'next' => self::TIER_2, 'nextPercent' => 1.5],
            ['min' => self::TIER_2, 'max' => self::TIER_3, 'percent' => 1.5, 'next' => self::TIER_3, 'nextPercent' => 2.0],
            ['min' => self::TIER_3, 'max' => null, 'percent' => 2.0, 'next' => null, 'nextPercent' => null],
        ];

        foreach ($thresholds as $tier) {
            if ($tier['max'] === null || $periodTotal < $tier['max']) {
                $progress = 1.0;
                if ($tier['next'] !== null && $tier['next'] > $tier['min']) {
                    $progress = min(1.0, max(0.0, ($periodTotal - $tier['min']) / ($tier['next'] - $tier['min'])));
                }

                return [
                    'percent' => $percent,
                    'nextThreshold' => $tier['next'],
                    'nextPercent' => $tier['nextPercent'],
                    'progress' => round($progress, 4),
                ];
            }
        }

        return [
            'percent' => 2.0,
            'nextThreshold' => null,
            'nextPercent' => null,
            'progress' => 1.0,
        ];
    }

    /**
     * Кэшбек за период: каждый диапазон оборота начисляется по своей ставке (накопительно, не от одного заказа).
     */
    public function calculateAccrualAmount(float $periodTotal): float
    {
        $periodTotal = max(0.0, $periodTotal);
        $bands = [
            [0.0, (float) self::TIER_1, 0.0],
            [(float) self::TIER_1, (float) self::TIER_2, 1.0],
            [(float) self::TIER_2, (float) self::TIER_3, 1.5],
            [(float) self::TIER_3, null, 2.0],
        ];

        $accrual = 0.0;
        foreach ($bands as [$min, $max, $rate]) {
            if ($periodTotal <= $min) {
                break;
            }

            $cap = $max ?? $periodTotal;
            $portion = min($periodTotal, $cap) - $min;
            if ($portion > 0) {
                $accrual += $portion * $rate / 100;
            }
        }

        return round($accrual, 2);
    }

    public function amountToNextThreshold(float $periodTotal): ?float
    {
        $progress = $this->getProgress($periodTotal);
        if ($progress['nextThreshold'] === null) {
            return null;
        }

        return round(max(0.0, $progress['nextThreshold'] - $periodTotal), 2);
    }
}
