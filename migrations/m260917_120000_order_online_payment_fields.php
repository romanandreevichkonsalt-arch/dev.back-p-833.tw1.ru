<?php

use yii\db\Migration;

class m260917_120000_order_online_payment_fields extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn('{{%orders}}', 'payment_status', $this->string(32)->null()->after('payment_method'));
        $this->addColumn('{{%orders}}', 'payment_provider', $this->string(32)->null()->after('payment_status'));
        $this->addColumn('{{%orders}}', 'payment_external_id', $this->string(64)->null()->after('payment_provider'));
        $this->addColumn('{{%orders}}', 'paid_at', $this->dateTime()->null()->after('payment_external_id'));

        $this->createIndex('idx_orders_payment_external_id', '{{%orders}}', 'payment_external_id');
    }

    public function safeDown(): void
    {
        $this->dropIndex('idx_orders_payment_external_id', '{{%orders}}');
        $this->dropColumn('{{%orders}}', 'paid_at');
        $this->dropColumn('{{%orders}}', 'payment_external_id');
        $this->dropColumn('{{%orders}}', 'payment_provider');
        $this->dropColumn('{{%orders}}', 'payment_status');
    }
}
