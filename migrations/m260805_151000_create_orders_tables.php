<?php

use yii\db\Migration;

class m260805_151000_create_orders_tables extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%orders}}', [
            'id' => $this->primaryKey(),
            'number' => $this->string(32)->notNull()->unique(),
            'user_id' => $this->integer()->null(),
            'customer_name' => $this->string(255)->notNull(),
            'customer_phone' => $this->string(20)->notNull(),
            'customer_email' => $this->string(255)->null(),
            'delivery_address' => $this->string(512)->null(),
            'comment' => $this->text()->null(),
            'status' => $this->string(32)->notNull()->defaultValue('new'),
            'total_amount' => $this->decimal(12, 2)->notNull()->defaultValue(0),
            'manager_comment' => $this->text()->null(),
            'assigned_to' => $this->integer()->null(),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);

        $this->createIndex('idx_orders_status', '{{%orders}}', 'status');
        $this->createIndex('idx_orders_created_at', '{{%orders}}', 'created_at');
        $this->addForeignKey('fk_orders_user_id', '{{%orders}}', 'user_id', '{{%users}}', 'id', 'SET NULL', 'CASCADE');
        $this->addForeignKey('fk_orders_assigned_to', '{{%orders}}', 'assigned_to', '{{%admin_users}}', 'id', 'SET NULL', 'CASCADE');

        $this->createTable('{{%order_items}}', [
            'id' => $this->primaryKey(),
            'order_id' => $this->integer()->notNull(),
            'product_title' => $this->string(255)->notNull(),
            'product_sku' => $this->string(64)->null(),
            'quantity' => $this->integer()->notNull()->defaultValue(1),
            'unit_price' => $this->decimal(12, 2)->notNull(),
            'line_total' => $this->decimal(12, 2)->notNull(),
        ]);
        $this->createIndex('idx_order_items_order_id', '{{%order_items}}', 'order_id');
        $this->addForeignKey('fk_order_items_order_id', '{{%order_items}}', 'order_id', '{{%orders}}', 'id', 'CASCADE', 'CASCADE');

        $this->createTable('{{%order_status_log}}', [
            'id' => $this->primaryKey(),
            'order_id' => $this->integer()->notNull(),
            'old_status' => $this->string(32)->null(),
            'new_status' => $this->string(32)->notNull(),
            'comment' => $this->text()->null(),
            'admin_user_id' => $this->integer()->null(),
            'created_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex('idx_order_status_log_order_id', '{{%order_status_log}}', 'order_id');
        $this->addForeignKey('fk_order_status_log_order_id', '{{%order_status_log}}', 'order_id', '{{%orders}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_order_status_log_admin_user_id', '{{%order_status_log}}', 'admin_user_id', '{{%admin_users}}', 'id', 'SET NULL', 'CASCADE');
    }

    public function safeDown(): void
    {
        $this->dropForeignKey('fk_order_status_log_admin_user_id', '{{%order_status_log}}');
        $this->dropForeignKey('fk_order_status_log_order_id', '{{%order_status_log}}');
        $this->dropTable('{{%order_status_log}}');

        $this->dropForeignKey('fk_order_items_order_id', '{{%order_items}}');
        $this->dropTable('{{%order_items}}');

        $this->dropForeignKey('fk_orders_assigned_to', '{{%orders}}');
        $this->dropForeignKey('fk_orders_user_id', '{{%orders}}');
        $this->dropTable('{{%orders}}');
    }
}
