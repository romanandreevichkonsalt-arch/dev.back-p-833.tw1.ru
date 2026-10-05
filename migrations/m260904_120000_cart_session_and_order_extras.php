<?php

use yii\db\Migration;

class m260904_120000_cart_session_and_order_extras extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn(
            '{{%guest_sessions}}',
            'cart_merged_at',
            $this->dateTime()->null()->after('favorites_merged_at')
        );

        $this->dropForeignKey('fk_cart_items_user_id', '{{%cart_items}}');
        $this->dropIndex('ux_cart_items_user_product', '{{%cart_items}}');

        $this->alterColumn('{{%cart_items}}', 'user_id', $this->integer()->null());
        $this->addColumn('{{%cart_items}}', 'session_id', $this->string(64)->null()->after('user_id'));
        $this->addColumn('{{%cart_items}}', 'comment', $this->text()->null()->after('quantity'));

        $this->createIndex('ux_cart_items_user_product', '{{%cart_items}}', ['user_id', 'catalog_product_id'], true);
        $this->createIndex('ux_cart_items_session_product', '{{%cart_items}}', ['session_id', 'catalog_product_id'], true);
        $this->createIndex('idx_cart_items_session_id', '{{%cart_items}}', 'session_id');

        $this->addForeignKey(
            'fk_cart_items_user_id',
            '{{%cart_items}}',
            'user_id',
            '{{%users}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->addColumn('{{%order_items}}', 'comment', $this->text()->null()->after('is_custom'));

        $this->addColumn('{{%orders}}', 'session_id', $this->string(64)->null()->after('user_id'));
        $this->addColumn('{{%orders}}', 'attachment_path', $this->string(512)->null()->after('comment'));
        $this->addColumn('{{%orders}}', 'attachment_original_name', $this->string(255)->null()->after('attachment_path'));
        $this->createIndex('idx_orders_session_id', '{{%orders}}', 'session_id');
    }

    public function safeDown(): bool
    {
        $this->dropIndex('idx_orders_session_id', '{{%orders}}');
        $this->dropColumn('{{%orders}}', 'attachment_original_name');
        $this->dropColumn('{{%orders}}', 'attachment_path');
        $this->dropColumn('{{%orders}}', 'session_id');

        $this->dropColumn('{{%order_items}}', 'comment');

        $this->dropForeignKey('fk_cart_items_user_id', '{{%cart_items}}');
        $this->dropIndex('idx_cart_items_session_id', '{{%cart_items}}');
        $this->dropIndex('ux_cart_items_session_product', '{{%cart_items}}');
        $this->dropIndex('ux_cart_items_user_product', '{{%cart_items}}');
        $this->dropColumn('{{%cart_items}}', 'comment');
        $this->dropColumn('{{%cart_items}}', 'session_id');
        $this->alterColumn('{{%cart_items}}', 'user_id', $this->integer()->notNull());
        $this->createIndex('ux_cart_items_user_product', '{{%cart_items}}', ['user_id', 'catalog_product_id'], true);
        $this->addForeignKey(
            'fk_cart_items_user_id',
            '{{%cart_items}}',
            'user_id',
            '{{%users}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->dropColumn('{{%guest_sessions}}', 'cart_merged_at');

        return true;
    }
}
