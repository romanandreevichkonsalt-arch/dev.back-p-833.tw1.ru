<?php

namespace app\services\dealer;

use app\exceptions\ApiValidationException;
use app\models\DealerCashbackAccount;
use app\models\DealerCashbackLedger;
use app\models\Order;
use app\models\User;
use Yii;

class CashbackService
{
    public function __construct(
        private readonly CashbackTierCalculator $tierCalculator = new CashbackTierCalculator(),
        private readonly DealerCashbackExpiryResolver $expiryResolver = new DealerCashbackExpiryResolver(),
    ) {
    }

    public function ensureAccount(int $userId): DealerCashbackAccount
    {
        $account = DealerCashbackAccount::findOne($userId);
        $currentPeriod = date('Y-m');

        if ($account === null) {
            $account = new DealerCashbackAccount([
                'user_id' => $userId,
                'balance' => 0,
                'period_total' => 0,
                'period_year_month' => $currentPeriod,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $account->save(false);

            return $account;
        }

        if ($account->period_year_month !== $currentPeriod) {
            $account->period_total = 0;
            $account->period_year_month = $currentPeriod;
            $account->updated_at = date('Y-m-d H:i:s');
            $account->save(false, ['period_total', 'period_year_month', 'updated_at']);
        }

        return $account;
    }

    /**
     * @return array<string, mixed>
     */
    public function getWidget(int $userId): array
    {
        $account = $this->ensureAccount($userId);
        $progress = $this->tierCalculator->getProgress((float)$account->period_total);

        $nextExpiring = $this->resolveNextExpiringBatch($userId, (float)$account->balance);

        return [
            'balance' => (float)$account->balance,
            'periodTotal' => (float)$account->period_total,
            'periodYearMonth' => $account->period_year_month,
            'currentPercent' => $progress['percent'],
            'nextThreshold' => $progress['nextThreshold'],
            'nextPercent' => $progress['nextPercent'],
            'amountToNextThreshold' => $this->tierCalculator->amountToNextThreshold((float)$account->period_total),
            'progress' => $progress['progress'],
            'nextExpiringAmount' => $nextExpiring['amount'],
            'nextExpiringAt' => $nextExpiring['expiresAt'],
        ];
    }

    /**
     * Ближайшая по дате сгорания порция баланса (FIFO по начислениям с одинаковым expires_at суммируются).
     *
     * @return array{amount: float|null, expiresAt: string|null}
     */
    public function resolveNextExpiringBatch(int $userId, ?float $balance = null): array
    {
        if ($balance === null) {
            $balance = (float)$this->ensureAccount($userId)->balance;
        }
        $balance = round(max(0, $balance), 2);
        if ($balance <= 0) {
            return ['amount' => null, 'expiresAt' => null];
        }

        $now = date('Y-m-d H:i:s');
        $accruals = DealerCashbackLedger::find()
            ->where(['user_id' => $userId, 'type' => DealerCashbackLedger::TYPE_ACCRUAL])
            ->andWhere(['not', ['expires_at' => null]])
            ->andWhere(['>', 'expires_at', $now])
            ->orderBy(['expires_at' => SORT_ASC, 'created_at' => SORT_ASC, 'id' => SORT_ASC])
            ->all();

        $remainingByExpiry = [];
        foreach ($accruals as $accrual) {
            if ($this->hasLedgerComment($userId, 'expire:' . $accrual->id)) {
                continue;
            }

            $lotAmount = round((float)$accrual->amount, 2);
            if ($lotAmount <= 0 || $balance <= 0) {
                continue;
            }

            $allocated = round(min($lotAmount, $balance), 2);
            $balance = round($balance - $allocated, 2);
            $expiresAt = (string)$accrual->expires_at;
            $remainingByExpiry[$expiresAt] = round(($remainingByExpiry[$expiresAt] ?? 0) + $allocated, 2);
        }

        if ($remainingByExpiry === []) {
            return ['amount' => null, 'expiresAt' => null];
        }

        ksort($remainingByExpiry);
        foreach ($remainingByExpiry as $expiresAt => $amount) {
            if ($amount > 0) {
                return ['amount' => $amount, 'expiresAt' => $expiresAt];
            }
        }

        return ['amount' => null, 'expiresAt' => null];
    }

    public function addOrderToPeriodTotal(int $userId, float $orderTotal): void
    {
        $account = $this->ensureAccount($userId);
        $account->period_total = round((float)$account->period_total + $orderTotal, 2);
        $account->updated_at = date('Y-m-d H:i:s');
        $account->save(false, ['period_total', 'updated_at']);
    }

    public function recordPaidOrderForPeriod(Order $order): void
    {
        $userId = (int)$order->user_id;
        if ($userId <= 0 || !$order->isPaymentPaid()) {
            return;
        }

        $orderId = (int)$order->id;
        if ($this->wasOrderCountedForPeriod($userId, $orderId)) {
            return;
        }

        $amount = (float)$order->subtotal_amount;
        if ($amount <= 0) {
            return;
        }

        $this->addOrderToPeriodTotal($userId, $amount);
        $account = $this->ensureAccount($userId);
        $this->writeLedger(
            $userId,
            DealerCashbackLedger::TYPE_ADJUSTMENT,
            0,
            (float)$account->balance,
            $account->period_year_month,
            null,
            $orderId,
            $this->periodAddComment($orderId),
        );
    }

    public function setBalanceManual(int $userId, float $newBalance): void
    {
        $newBalance = round(max(0, $newBalance), 2);
        $account = $this->ensureAccount($userId);
        $current = round((float)$account->balance, 2);
        if ($newBalance === $current) {
            return;
        }

        $delta = round($newBalance - $current, 2);
        $account->balance = $newBalance;
        $account->updated_at = date('Y-m-d H:i:s');
        $account->save(false, ['balance', 'updated_at']);

        $this->writeLedger(
            $userId,
            DealerCashbackLedger::TYPE_ADJUSTMENT,
            $delta,
            $newBalance,
            null,
            null,
            null,
            'Ручная корректировка баланса',
        );
    }

    public function subtractOrderFromPeriodTotal(int $userId, float $orderTotal, int $orderId): void
    {
        if ($orderTotal <= 0 || $this->hasLedgerComment($userId, 'period_revert:order:' . $orderId)) {
            return;
        }

        $account = $this->ensureAccount($userId);
        $account->period_total = round(max(0, (float)$account->period_total - $orderTotal), 2);
        $account->updated_at = date('Y-m-d H:i:s');
        $account->save(false, ['period_total', 'updated_at']);

        $this->writeLedger(
            $userId,
            DealerCashbackLedger::TYPE_ADJUSTMENT,
            0,
            (float)$account->balance,
            $account->period_year_month,
            null,
            null,
            'period_revert:order:' . $orderId,
        );
    }

    public function handleOrderCancelled(Order $order): void
    {
        $userId = (int)$order->user_id;
        if ($userId <= 0) {
            return;
        }

        $orderMonth = date('Y-m', strtotime((string)$order->created_at));
        $account = $this->ensureAccount($userId);
        if ($orderMonth === $account->period_year_month && $this->wasOrderCountedForPeriod($userId, (int)$order->id)) {
            $this->subtractOrderFromPeriodTotal($userId, (float)$order->subtotal_amount, (int)$order->id);
        }

        $this->refundSpendForCancelledOrder($userId, (int)$order->id, (float)$order->cashback_used_amount);
    }

    public function refundSpendForCancelledOrder(int $userId, int $orderId, float $spentAmount): float
    {
        if ($spentAmount <= 0 || $this->hasLedgerComment($userId, 'refund:order:' . $orderId)) {
            return 0.0;
        }

        $spendEntry = DealerCashbackLedger::find()
            ->where([
                'user_id' => $userId,
                'order_id' => $orderId,
                'type' => DealerCashbackLedger::TYPE_SPEND,
            ])
            ->one();
        if ($spendEntry === null) {
            return 0.0;
        }

        if (!$this->canRefundSpendFromActiveAccruals($userId, (string)$spendEntry->created_at)) {
            return 0.0;
        }

        $account = $this->ensureAccount($userId);
        $account->balance = round((float)$account->balance + $spentAmount, 2);
        $account->updated_at = date('Y-m-d H:i:s');
        $account->save(false, ['balance', 'updated_at']);

        $this->writeLedger(
            $userId,
            DealerCashbackLedger::TYPE_ADJUSTMENT,
            $spentAmount,
            (float)$account->balance,
            null,
            null,
            $orderId,
            'refund:order:' . $orderId,
        );

        return $spentAmount;
    }

    public function notifyExpiringSoon(int $daysBefore = 7): int
    {
        $targetDay = date('Y-m-d', strtotime('+' . $daysBefore . ' days'));
        $from = $targetDay . ' 00:00:00';
        $to = date('Y-m-d H:i:s', strtotime($from . ' +1 day'));

        $entries = DealerCashbackLedger::find()
            ->where(['type' => DealerCashbackLedger::TYPE_ACCRUAL])
            ->andWhere(['>=', 'expires_at', $from])
            ->andWhere(['<', 'expires_at', $to])
            ->all();

        $mailer = new CashbackExpiryMailer();
        $count = 0;

        foreach ($entries as $entry) {
            if ($this->hasLedgerComment((int)$entry->user_id, 'notify:' . $entry->id)) {
                continue;
            }

            $user = User::findOne((int)$entry->user_id);
            if ($user === null || !$user->isDealer()) {
                continue;
            }

            $profile = $user->dealerProfile;
            if ($profile === null) {
                continue;
            }

            if (!$mailer->send($user, $profile, (float)$entry->amount, (string)$entry->expires_at)) {
                continue;
            }

            $account = $this->ensureAccount((int)$entry->user_id);
            $this->writeLedger(
                (int)$entry->user_id,
                DealerCashbackLedger::TYPE_NOTIFY,
                0,
                (float)$account->balance,
                $entry->period_year_month,
                null,
                null,
                'notify:' . $entry->id,
            );
            $count++;
        }

        return $count;
    }

    public function spend(int $userId, float $amount, int $orderId): void
    {
        if ($amount <= 0) {
            return;
        }

        $account = $this->ensureAccount($userId);
        if ((float)$account->balance < $amount) {
            throw new ApiValidationException('Недостаточно кэшбека.');
        }

        $account->balance = round((float)$account->balance - $amount, 2);
        $account->updated_at = date('Y-m-d H:i:s');
        $account->save(false, ['balance', 'updated_at']);

        $this->writeLedger($userId, DealerCashbackLedger::TYPE_SPEND, -$amount, (float)$account->balance, null, null, $orderId, 'Списание при заказе');
    }

    public function accrueForPeriod(int $userId, string $periodYearMonth, float $periodTotal): float
    {
        $amount = $this->tierCalculator->calculateAccrualAmount($periodTotal);
        if ($amount <= 0) {
            return 0.0;
        }

        $account = $this->ensureAccount($userId);
        $account->balance = round((float)$account->balance + $amount, 2);
        $account->updated_at = date('Y-m-d H:i:s');
        $account->save(false, ['balance', 'updated_at']);

        $days = $this->expiryResolver->expiryDaysForDealer($userId);
        $expiresAt = date('Y-m-d H:i:s', strtotime('+' . $days . ' days'));
        $this->writeLedger(
            $userId,
            DealerCashbackLedger::TYPE_ACCRUAL,
            $amount,
            (float)$account->balance,
            $periodYearMonth,
            $expiresAt,
            null,
            'Начисление за ' . $periodYearMonth
        );

        return $amount;
    }

    public function expireDueEntries(): int
    {
        $entries = DealerCashbackLedger::find()
            ->where(['type' => DealerCashbackLedger::TYPE_ACCRUAL])
            ->andWhere(['not', ['expires_at' => null]])
            ->andWhere(['<=', 'expires_at', date('Y-m-d H:i:s')])
            ->all();

        $count = 0;
        foreach ($entries as $entry) {
            $alreadyExpired = DealerCashbackLedger::find()
                ->where([
                    'type' => DealerCashbackLedger::TYPE_EXPIRE,
                    'user_id' => $entry->user_id,
                    'comment' => 'expire:' . $entry->id,
                ])
                ->exists();
            if ($alreadyExpired) {
                continue;
            }

            $account = $this->ensureAccount((int)$entry->user_id);
            $amount = min((float)$entry->amount, (float)$account->balance);
            if ($amount <= 0) {
                continue;
            }

            $account->balance = round((float)$account->balance - $amount, 2);
            $account->updated_at = date('Y-m-d H:i:s');
            $account->save(false, ['balance', 'updated_at']);

            $this->writeLedger(
                (int)$entry->user_id,
                DealerCashbackLedger::TYPE_EXPIRE,
                -$amount,
                (float)$account->balance,
                $entry->period_year_month,
                null,
                null,
                'expire:' . $entry->id
            );
            $count++;
        }

        return $count;
    }

    public function accrueMonthlyForAll(string $periodYearMonth): int
    {
        $dealers = User::find()->where(['type' => User::TYPE_DEALER])->all();
        $count = 0;

        foreach ($dealers as $dealer) {
            $from = $periodYearMonth . '-01 00:00:00';
            $to = date('Y-m-d H:i:s', strtotime($from . ' +1 month'));
            $total = (float)Order::find()
                ->where(['user_id' => (int)$dealer->id])
                ->andWhere(['>=', 'created_at', $from])
                ->andWhere(['<', 'created_at', $to])
                ->andWhere(['not in', 'status', [Order::STATUS_CANCELLED]])
                ->andWhere(['payment_status' => \app\services\order\OrderPaymentMapper::PAYMENT_STATUS_PAID])
                ->sum('subtotal_amount');

            if ($total <= 0) {
                $total = (float)Order::find()
                    ->where(['user_id' => (int)$dealer->id])
                    ->andWhere(['>=', 'created_at', $from])
                    ->andWhere(['<', 'created_at', $to])
                    ->andWhere(['not in', 'status', [Order::STATUS_CANCELLED]])
                    ->andWhere(['payment_status' => \app\services\order\OrderPaymentMapper::PAYMENT_STATUS_PAID])
                    ->sum('total_amount');
            }

            if ($this->accrueForPeriod((int)$dealer->id, $periodYearMonth, $total) > 0) {
                $count++;
            }
        }

        return $count;
    }

    private function canRefundSpendFromActiveAccruals(int $userId, string $spentAt): bool
    {
        return DealerCashbackLedger::find()
            ->where(['user_id' => $userId, 'type' => DealerCashbackLedger::TYPE_ACCRUAL])
            ->andWhere(['<=', 'created_at', $spentAt])
            ->andWhere(['>', 'expires_at', $spentAt])
            ->andWhere(['>', 'expires_at', date('Y-m-d H:i:s')])
            ->exists();
    }

    private function wasOrderCountedForPeriod(int $userId, int $orderId): bool
    {
        return $this->hasLedgerComment($userId, $this->periodAddComment($orderId));
    }

    private function periodAddComment(int $orderId): string
    {
        return 'period_add:order:' . $orderId;
    }

    private function hasLedgerComment(int $userId, string $comment): bool
    {
        return DealerCashbackLedger::find()
            ->where(['user_id' => $userId, 'comment' => $comment])
            ->exists();
    }

    private function writeLedger(
        int $userId,
        string $type,
        float $amount,
        float $balanceAfter,
        ?string $periodYearMonth,
        ?string $expiresAt,
        ?int $orderId,
        ?string $comment,
    ): void {
        $ledger = new DealerCashbackLedger([
            'user_id' => $userId,
            'type' => $type,
            'amount' => $amount,
            'balance_after' => $balanceAfter,
            'period_year_month' => $periodYearMonth,
            'expires_at' => $expiresAt,
            'order_id' => $orderId,
            'comment' => $comment,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $ledger->save(false);
    }
}
