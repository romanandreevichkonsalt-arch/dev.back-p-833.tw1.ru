<?php

namespace app\commands;

use app\models\Order;
use app\services\order\OrderPaymentMapper;
use Yii;
use yii\console\Controller;

class OrderController extends Controller
{
    /**
     * Cancel guest orders stuck in pending_payment longer than N hours.
     *
     * Cron example (daily): 15 4 * * * php yii order/cancel-unpaid
     */
    public function actionCancelUnpaid(?int $hours = null): int
    {
        $hours = $hours ?? (int)(Yii::$app->params['orderUnpaidCancelHours'] ?? 24);
        if ($hours < 1) {
            $hours = 24;
        }

        $threshold = date('Y-m-d H:i:s', time() - ($hours * 3600));
        $orders = Order::find()
            ->where([
                'status' => Order::STATUS_PENDING_PAYMENT,
                'payment_status' => OrderPaymentMapper::PAYMENT_STATUS_PENDING,
            ])
            ->andWhere(['<', 'created_at', $threshold])
            ->all();

        $count = 0;
        foreach ($orders as $order) {
            $order->status = Order::STATUS_CANCELLED;
            $order->payment_status = OrderPaymentMapper::PAYMENT_STATUS_CANCELLED;
            $order->save(false, ['status', 'payment_status', 'updated_at']);
            $count++;
        }

        $this->stdout("Отменено неоплаченных заказов: {$count} (старше {$hours} ч.).\n");

        return self::EXIT_CODE_NORMAL;
    }
}
