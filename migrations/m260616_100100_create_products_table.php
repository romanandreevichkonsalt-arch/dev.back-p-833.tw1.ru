<?php

use yii\db\Migration;

class m260616_100100_create_products_table extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%products}}', [
            'id' => $this->primaryKey(),
            'name' => $this->string(255)->notNull(),
            'slug' => $this->string(255)->notNull()->unique(),
            'sku' => $this->string(64)->notNull()->unique(),
            'description' => $this->text()->null(),
            'price' => $this->decimal(12, 2)->notNull()->defaultValue(0),
            'old_price' => $this->decimal(12, 2)->null(),
            'is_active' => $this->boolean()->notNull()->defaultValue(true),
            'stock_qty' => $this->integer()->notNull()->defaultValue(0),
            'seo_title' => $this->string(255)->null(),
            'seo_description' => $this->string(512)->null(),
            'seo_h1' => $this->string(255)->null(),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);

        $this->createIndex('idx_products_is_active', '{{%products}}', 'is_active');
        $this->createIndex('idx_products_price', '{{%products}}', 'price');
        $this->createIndex('idx_products_stock_qty', '{{%products}}', 'stock_qty');
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%products}}');
    }
}
