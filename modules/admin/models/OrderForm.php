<?php

namespace app\modules\admin\models;

use app\models\Order;
use app\models\OrderItem;
use app\models\OrderStatusLog;
use Yii;
use yii\base\Model;

class OrderForm extends Model
{
    public string $customer_name = '';
    public string $customer_phone = '';
    public ?string $customer_email = null;
    public ?string $delivery_address = null;
    public ?string $comment = null;
    public ?string $manager_comment = null;
    public ?int $assigned_to = null;
    public string $status = Order::STATUS_NEW;

    /** @var array<int, array{product_title:string,product_sku:?string,quantity:int,unit_price:float}> */
    public array $items = [];

    public function rules(): array
    {
        return [
            [['customer_name', 'customer_phone'], 'required'],
            [['customer_name', 'delivery_address'], 'string', 'max' => 255],
            [['customer_phone'], 'match', 'pattern' => '/^\+7\d{10}$/'],
            [['customer_email'], 'email'],
            [['comment', 'manager_comment'], 'string'],
            [['assigned_to'], 'integer'],
            [['status'], 'in', 'range' => array_keys(Order::statusLabels())],
            [['items'], 'validateItems'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'customer_name' => 'Клиент',
            'customer_phone' => 'Телефон',
            'customer_email' => 'Email',
            'delivery_address' => 'Адрес доставки',
            'comment' => 'Комментарий клиента',
            'manager_comment' => 'Комментарий менеджера',
            'assigned_to' => 'Ответственный',
            'status' => 'Статус',
        ];
    }

    public function validateItems(string $attribute): void
    {
        if ($this->items === []) {
            $this->addError($attribute, 'Добавьте хотя бы одну позицию заказа.');
            return;
        }

        foreach ($this->items as $index => $item) {
            if (trim((string)($item['product_title'] ?? '')) === '') {
                $this->addError($attribute, 'Укажите название товара в позиции ' . ($index + 1) . '.');
            }
        }
    }

    public function loadDefaultItems(): void
    {
        if ($this->items === []) {
            $this->items = [
                [
                    'product_title' => '',
                    'product_sku' => '',
                    'quantity' => 1,
                    'unit_price' => 0,
                ],
                [
                    'product_title' => '',
                    'product_sku' => '',
                    'quantity' => 1,
                    'unit_price' => 0,
                ],
            ];
        }
    }

    public function save(): ?Order
    {
        if (!$this->validate()) {
            return null;
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            $order = new Order([
                'number' => Order::generateNumber(),
                'customer_name' => $this->customer_name,
                'customer_phone' => $this->customer_phone,
                'customer_email' => $this->customer_email,
                'delivery_address' => $this->delivery_address,
                'comment' => $this->comment,
                'manager_comment' => $this->manager_comment,
                'assigned_to' => $this->assigned_to,
                'status' => $this->status,
                'total_amount' => 0,
            ]);

            if (!$order->save()) {
                $this->addErrors($order->getErrors());
                $transaction->rollBack();
                return null;
            }

            foreach ($this->items as $row) {
                $item = new OrderItem([
                    'order_id' => (int)$order->id,
                    'product_title' => trim((string)$row['product_title']),
                    'product_sku' => trim((string)($row['product_sku'] ?? '')) ?: null,
                    'quantity' => max(1, (int)($row['quantity'] ?? 1)),
                    'unit_price' => (float)($row['unit_price'] ?? 0),
                ]);
                $item->beforeValidate();
                if (!$item->save()) {
                    $this->addErrors($item->getErrors());
                    $transaction->rollBack();
                    return null;
                }
            }

            $order->refresh();
            $order->recalculateTotal();
            $order->save(false, ['total_amount', 'updated_at']);

            $log = new OrderStatusLog([
                'order_id' => (int)$order->id,
                'old_status' => null,
                'new_status' => $order->status,
                'comment' => 'Заказ создан в админке',
                'admin_user_id' => Yii::$app->adminUser->id,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $log->save(false);

            $transaction->commit();

            return $order;
        } catch (\Throwable $e) {
            $transaction->rollBack();
            $this->addError('customer_name', $e->getMessage());
            return null;
        }
    }
}
