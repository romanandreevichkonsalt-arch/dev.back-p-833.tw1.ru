<?php

use yii\db\Migration;

class m260819_161000_cart_and_order_item_custom extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%cart_items}}', [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer()->notNull(),
            'catalog_product_id' => $this->integer()->notNull(),
            'quantity' => $this->integer()->notNull()->defaultValue(1),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);

        $this->createIndex('ux_cart_items_user_product', '{{%cart_items}}', ['user_id', 'catalog_product_id'], true);
        $this->createIndex('idx_cart_items_user_id', '{{%cart_items}}', 'user_id');
        $this->addForeignKey(
            'fk_cart_items_user_id',
            '{{%cart_items}}',
            'user_id',
            '{{%users}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
        $this->addForeignKey(
            'fk_cart_items_catalog_product_id',
            '{{%cart_items}}',
            'catalog_product_id',
            '{{%catalog_products}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->addColumn(
            '{{%order_items}}',
            'is_custom',
            $this->boolean()->notNull()->defaultValue(false)->after('product_sku')
                ->comment('SKU «кастом» на момент оформления заказа')
        );
    }

    public function safeDown(): bool
    {
        $this->dropColumn('{{%order_items}}', 'is_custom');

        $this->dropForeignKey('fk_cart_items_catalog_product_id', '{{%cart_items}}');
        $this->dropForeignKey('fk_cart_items_user_id', '{{%cart_items}}');
        $this->dropTable('{{%cart_items}}');

        return true;
    }
}
