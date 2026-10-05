<?php

use yii\db\Migration;

class m260831_180000_favorites extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%guest_sessions}}', [
            'session_id' => $this->string(64)->notNull(),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
            'favorites_merged_at' => $this->dateTime()->null(),
        ]);
        $this->addPrimaryKey('pk_guest_sessions', '{{%guest_sessions}}', 'session_id');

        $this->createTable('{{%favorite_items}}', [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer()->null(),
            'session_id' => $this->string(64)->null(),
            'catalog_product_id' => $this->integer()->notNull(),
            'created_at' => $this->dateTime()->notNull(),
        ]);

        $this->createIndex(
            'ux_favorite_items_user_product',
            '{{%favorite_items}}',
            ['user_id', 'catalog_product_id'],
            true
        );
        $this->createIndex(
            'ux_favorite_items_session_product',
            '{{%favorite_items}}',
            ['session_id', 'catalog_product_id'],
            true
        );
        $this->createIndex('idx_favorite_items_user_id', '{{%favorite_items}}', 'user_id');
        $this->createIndex('idx_favorite_items_session_id', '{{%favorite_items}}', 'session_id');

        $this->addForeignKey(
            'fk_favorite_items_user_id',
            '{{%favorite_items}}',
            'user_id',
            '{{%users}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
        $this->addForeignKey(
            'fk_favorite_items_catalog_product_id',
            '{{%favorite_items}}',
            'catalog_product_id',
            '{{%catalog_products}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
    }

    public function safeDown(): bool
    {
        $this->dropForeignKey('fk_favorite_items_catalog_product_id', '{{%favorite_items}}');
        $this->dropForeignKey('fk_favorite_items_user_id', '{{%favorite_items}}');
        $this->dropTable('{{%favorite_items}}');
        $this->dropTable('{{%guest_sessions}}');

        return true;
    }
}
