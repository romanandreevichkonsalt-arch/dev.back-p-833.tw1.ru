<?php

use yii\db\Migration;

class m260616_100200_create_product_categories_table extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%product_categories}}', [
            'product_id' => $this->integer()->notNull(),
            'category_id' => $this->integer()->notNull(),
        ]);

        $this->addPrimaryKey('pk_product_categories', '{{%product_categories}}', ['product_id', 'category_id']);

        $this->addForeignKey(
            'fk_product_categories_product_id',
            '{{%product_categories}}',
            'product_id',
            '{{%products}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->addForeignKey(
            'fk_product_categories_category_id',
            '{{%product_categories}}',
            'category_id',
            '{{%categories}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->createIndex('idx_product_categories_category_id', '{{%product_categories}}', 'category_id');
    }

    public function safeDown(): void
    {
        $this->dropForeignKey('fk_product_categories_category_id', '{{%product_categories}}');
        $this->dropForeignKey('fk_product_categories_product_id', '{{%product_categories}}');
        $this->dropTable('{{%product_categories}}');
    }
}
