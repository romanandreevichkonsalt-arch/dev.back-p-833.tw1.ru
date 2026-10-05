<?php

namespace tests\unit\services;

use app\models\DealerCashbackAccount;
use app\models\DealerCashbackLedger;
use app\models\DealerProfile;
use app\models\Order;
use app\models\User;
use app\services\dealer\CashbackService;
use Codeception\Test\Unit;
use Yii;

class CashbackServiceTest extends Unit
{
    private const USERNAME = 'unit-cashback-service-dealer';

    protected function _before(): void
    {
        parent::_before();
        Yii::$app->db->open();

        $user = User::findOne(['username' => self::USERNAME]);
        if ($user !== null) {
            DealerCashbackLedger::deleteAll(['user_id' => (int)$user->id]);
            DealerCashbackAccount::deleteAll(['user_id' => (int)$user->id]);
            Order::deleteAll(['user_id' => (int)$user->id]);
            DealerProfile::deleteAll(['user_id' => (int)$user->id]);
            $user->delete();
        }
    }

    public function testRecordPaidOrderForPeriodIsIdempotent(): void
    {
        $dealer = $this->createDealer();
        $service = new CashbackService();

        $order = new Order([
            'number' => 'CB-PAID-' . substr(uniqid(), -5),
            'user_id' => (int)$dealer->id,
            'customer_name' => 'Дилер',
            'customer_phone' => '+79991112233',
            'status' => Order::STATUS_NEW,
            'subtotal_amount' => 25_000,
            'total_amount' => 25_000,
        ]);
        $order->save(false);
        if ($order->hasAttribute('payment_status')) {
            Order::updateAll(
                ['payment_status' => \app\services\order\OrderPaymentMapper::PAYMENT_STATUS_PAID],
                ['id' => (int)$order->id]
            );
            $order->refresh();
        } else {
            $this->markTestSkipped('Колонка orders.payment_status недоступна в тестовой БД.');
        }

        $service->recordPaidOrderForPeriod($order);
        $service->recordPaidOrderForPeriod($order);

        $account = $service->ensureAccount((int)$dealer->id);
        $this->assertSame(25_000.0, (float)$account->period_total);
    }

    public function testSetBalanceManualDoesNotChangeAccrualExpiry(): void
    {
        $dealer = $this->createDealer();
        $service = new CashbackService();
        $expiresAt = date('Y-m-d H:i:s', strtotime('+15 days'));

        $accrual = new DealerCashbackLedger([
            'user_id' => (int)$dealer->id,
            'type' => DealerCashbackLedger::TYPE_ACCRUAL,
            'amount' => 1_000,
            'balance_after' => 1_000,
            'period_year_month' => date('Y-m'),
            'expires_at' => $expiresAt,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $accrual->save(false);

        $service->setBalanceManual((int)$dealer->id, 2_500);

        $accrual->refresh();
        $this->assertSame($expiresAt, $accrual->expires_at);
    }

    public function testSubtractOrderFromPeriodTotalIsIdempotent(): void
    {
        $dealer = $this->createDealer();
        $service = new CashbackService();
        $account = $service->ensureAccount((int)$dealer->id);
        $account->period_total = 50_000;
        $account->save(false, ['period_total', 'updated_at']);

        $service->subtractOrderFromPeriodTotal((int)$dealer->id, 10_000, 101);
        $service->subtractOrderFromPeriodTotal((int)$dealer->id, 10_000, 101);

        $account->refresh();
        $this->assertSame(40_000.0, (float)$account->period_total);
    }

    public function testRefundSpendWhenAccrualStillActive(): void
    {
        $dealer = $this->createDealer();
        $service = new CashbackService();
        $account = $service->ensureAccount((int)$dealer->id);
        $account->balance = 5_000;
        $account->save(false, ['balance', 'updated_at']);

        $accrual = new DealerCashbackLedger([
            'user_id' => (int)$dealer->id,
            'type' => DealerCashbackLedger::TYPE_ACCRUAL,
            'amount' => 5_000,
            'balance_after' => 5_000,
            'period_year_month' => date('Y-m'),
            'expires_at' => date('Y-m-d H:i:s', strtotime('+20 days')),
            'created_at' => date('Y-m-d H:i:s', strtotime('-5 days')),
        ]);
        $accrual->save(false);

        $order = new Order([
            'number' => 'CB-REF-' . substr(uniqid(), -5),
            'user_id' => (int)$dealer->id,
            'customer_name' => 'Дилер',
            'customer_phone' => '+79991112233',
            'status' => Order::STATUS_NEW,
            'subtotal_amount' => 10_000,
            'cashback_used_amount' => 3_000,
            'total_amount' => 7_000,
        ]);
        $order->save(false);

        $spend = new DealerCashbackLedger([
            'user_id' => (int)$dealer->id,
            'type' => DealerCashbackLedger::TYPE_SPEND,
            'amount' => -3_000,
            'balance_after' => 2_000,
            'order_id' => (int)$order->id,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $spend->save(false);

        $account->balance = 2_000;
        $account->save(false, ['balance', 'updated_at']);

        $refunded = $service->refundSpendForCancelledOrder((int)$dealer->id, (int)$order->id, 3_000);
        $this->assertSame(3_000.0, $refunded);

        $account->refresh();
        $this->assertSame(5_000.0, (float)$account->balance);
        $this->assertTrue(
            DealerCashbackLedger::find()
                ->where(['user_id' => (int)$dealer->id, 'comment' => 'refund:order:' . $order->id])
                ->exists()
        );
    }

    public function testRefundSpendSkippedWhenAccrualExpired(): void
    {
        $dealer = $this->createDealer();
        $service = new CashbackService();

        $accrual = new DealerCashbackLedger([
            'user_id' => (int)$dealer->id,
            'type' => DealerCashbackLedger::TYPE_ACCRUAL,
            'amount' => 5_000,
            'balance_after' => 5_000,
            'period_year_month' => date('Y-m', strtotime('-2 months')),
            'expires_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'created_at' => date('Y-m-d H:i:s', strtotime('-40 days')),
        ]);
        $accrual->save(false);

        $order = new Order([
            'number' => 'CB-NO-' . substr(uniqid(), -5),
            'user_id' => (int)$dealer->id,
            'customer_name' => 'Дилер',
            'customer_phone' => '+79991112233',
            'status' => Order::STATUS_CANCELLED,
            'subtotal_amount' => 10_000,
            'cashback_used_amount' => 3_000,
            'total_amount' => 7_000,
        ]);
        $order->save(false);

        $spend = new DealerCashbackLedger([
            'user_id' => (int)$dealer->id,
            'type' => DealerCashbackLedger::TYPE_SPEND,
            'amount' => -3_000,
            'balance_after' => 0,
            'order_id' => (int)$order->id,
            'created_at' => date('Y-m-d H:i:s', strtotime('-35 days')),
        ]);
        $spend->save(false);

        $refunded = $service->refundSpendForCancelledOrder((int)$dealer->id, (int)$order->id, 3_000);
        $this->assertSame(0.0, $refunded);
    }

    public function testResolveNextExpiringBatchFifoAndSameDateSum(): void
    {
        $dealer = $this->createDealer();
        $service = new CashbackService();
        $account = $service->ensureAccount((int)$dealer->id);
        $account->balance = 12_000;
        $account->save(false, ['balance', 'updated_at']);

        $soon = date('Y-m-d H:i:s', strtotime('+10 days'));
        $later = date('Y-m-d H:i:s', strtotime('+40 days'));

        foreach ([
            ['amount' => 1_000, 'expires_at' => $soon, 'created_at' => date('Y-m-d H:i:s', strtotime('-3 days'))],
            ['amount' => 500, 'expires_at' => $soon, 'created_at' => date('Y-m-d H:i:s', strtotime('-2 days'))],
            ['amount' => 20_000, 'expires_at' => $later, 'created_at' => date('Y-m-d H:i:s', strtotime('-1 day'))],
        ] as $row) {
            $accrual = new DealerCashbackLedger([
                'user_id' => (int)$dealer->id,
                'type' => DealerCashbackLedger::TYPE_ACCRUAL,
                'amount' => $row['amount'],
                'balance_after' => 0,
                'period_year_month' => date('Y-m'),
                'expires_at' => $row['expires_at'],
                'created_at' => $row['created_at'],
            ]);
            $accrual->save(false);
        }

        $next = $service->resolveNextExpiringBatch((int)$dealer->id);
        $this->assertSame(1_500.0, $next['amount']);
        $this->assertSame($soon, $next['expiresAt']);

        $widget = $service->getWidget((int)$dealer->id);
        $this->assertSame(1_500.0, $widget['nextExpiringAmount']);
        $this->assertSame($soon, $widget['nextExpiringAt']);
    }

    public function testResolveNextExpiringBatchCapsByBalance(): void
    {
        $dealer = $this->createDealer();
        $service = new CashbackService();
        $account = $service->ensureAccount((int)$dealer->id);
        $account->balance = 300;
        $account->save(false, ['balance', 'updated_at']);

        $expiresAt = date('Y-m-d H:i:s', strtotime('+5 days'));
        $accrual = new DealerCashbackLedger([
            'user_id' => (int)$dealer->id,
            'type' => DealerCashbackLedger::TYPE_ACCRUAL,
            'amount' => 1_000,
            'balance_after' => 1_000,
            'period_year_month' => date('Y-m'),
            'expires_at' => $expiresAt,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $accrual->save(false);

        $next = $service->resolveNextExpiringBatch((int)$dealer->id);
        $this->assertSame(300.0, $next['amount']);
        $this->assertSame($expiresAt, $next['expiresAt']);
    }

    public function testResolveNextExpiringBatchNullWhenNoActiveAccruals(): void
    {
        $dealer = $this->createDealer();
        $service = new CashbackService();
        $account = $service->ensureAccount((int)$dealer->id);
        $account->balance = 500;
        $account->save(false, ['balance', 'updated_at']);

        $next = $service->resolveNextExpiringBatch((int)$dealer->id);
        $this->assertNull($next['amount']);
        $this->assertNull($next['expiresAt']);
    }

    public function testNotifyExpiringSoonIsIdempotent(): void
    {
        $dealer = $this->createDealer();
        $service = new CashbackService();

        $expiresAt = date('Y-m-d 12:00:00', strtotime('+7 days'));
        $accrual = new DealerCashbackLedger([
            'user_id' => (int)$dealer->id,
            'type' => DealerCashbackLedger::TYPE_ACCRUAL,
            'amount' => 1_000,
            'balance_after' => 1_000,
            'period_year_month' => date('Y-m'),
            'expires_at' => $expiresAt,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $accrual->save(false);

        $first = $service->notifyExpiringSoon(7);
        $second = $service->notifyExpiringSoon(7);

        $this->assertSame(1, $first);
        $this->assertSame(0, $second);
        $this->assertTrue(
            DealerCashbackLedger::find()
                ->where(['user_id' => (int)$dealer->id, 'comment' => 'notify:' . $accrual->id])
                ->exists()
        );
    }

    private function createDealer(): User
    {
        $user = new User([
            'username' => self::USERNAME,
            'type' => User::TYPE_DEALER,
            'phone' => '79990007788',
            'password_hash' => 'x',
        ]);
        $user->save(false);

        $profile = new DealerProfile([
            'user_id' => (int)$user->id,
            'inn' => '7707083893',
            'company_name' => 'Cashback Test Dealer',
            'email' => 'cashback-test@example.com',
        ]);
        $profile->save(false);

        return User::findOne((int)$user->id);
    }
}
