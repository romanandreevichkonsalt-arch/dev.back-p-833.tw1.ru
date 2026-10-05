<?php

namespace tests\unit\services;

use app\models\DealerProfile;
use app\models\Order;
use app\models\OrderItem;
use app\models\User;
use app\services\order\OrderManagerNotificationMailer;
use Codeception\Test\Unit;
use Yii;

class OrderManagerNotificationMailerTest extends Unit
{
    private const USERNAME = 'unit-order-notify-dealer';

    protected function _before(): void
    {
        parent::_before();
        Yii::$app->db->open();

        $user = User::findOne(['username' => self::USERNAME]);
        if ($user !== null) {
            DealerProfile::deleteAll(['user_id' => (int)$user->id]);
            Order::deleteAll(['user_id' => (int)$user->id]);
            $user->delete();
        }

        Yii::$app->params['leadsNotifyEmail'] = 'manager-notify@example.com';
    }

    public function testSendsDealerOrderNotification(): void
    {
        $dealer = $this->createDealer();
        $order = $this->createOrder($dealer);

        $sent = (new OrderManagerNotificationMailer())->notifyDealerOrderCreated($order, $dealer);

        $this->assertTrue($sent);
    }

    private function createDealer(): User
    {
        $user = new User([
            'username' => self::USERNAME,
            'type' => User::TYPE_DEALER,
            'phone' => '79990001122',
            'password_hash' => 'x',
        ]);
        $user->save(false);

        $profile = new DealerProfile([
            'user_id' => (int)$user->id,
            'inn' => '7707083893',
            'company_name' => 'ООО Уведомление',
            'email' => 'dealer@example.com',
        ]);
        $profile->save(false);

        $user->refresh();

        return $user;
    }

    private function createOrder(User $dealer): Order
    {
        $order = new Order([
            'number' => Order::generateNumber(),
            'user_id' => (int)$dealer->id,
            'customer_name' => 'Клиент заказа',
            'customer_phone' => '+79991112233',
            'customer_email' => 'client@example.com',
            'delivery_address' => 'Москва',
            'payment_method' => 'cashless',
            'cashless_surcharge_amount' => 0,
            'status' => Order::STATUS_NEW,
            'subtotal_amount' => 10000,
            'promo_discount_amount' => 0,
            'cashback_used_amount' => 0,
            'total_amount' => 10000,
        ]);
        $order->save(false);

        $item = new OrderItem([
            'order_id' => (int)$order->id,
            'product_title' => 'Диван тест',
            'product_sku' => 'test-sku',
            'quantity' => 1,
            'unit_price' => 10000,
            'retail_unit_price' => 10000,
            'paid_line_total' => 10000,
            'comment' => 'Срочно',
        ]);
        $item->beforeValidate();
        $item->save(false);

        $order->refresh();
        $order->populateRelation('items', [$item]);

        return $order;
    }
}
