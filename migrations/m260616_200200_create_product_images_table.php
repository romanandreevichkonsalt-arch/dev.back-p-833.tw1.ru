<?php

use yii\db\Migration;

class m260616_200200_create_product_images_table extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%product_images}}', [
            'id' => $this->primaryKey(),
            'product_id' => $this->integer()->notNull(),
            'path' => $this->string(512)->notNull(),
            'alt' => $this->string(255)->null(),
            'sort_order' => $this->integer()->notNull()->defaultValue(0),
            'is_main' => $this->boolean()->notNull()->defaultValue(false),
            'created_at' => $this->dateTime()->notNull(),
        ]);

        $this->createIndex('idx_product_images_product_id', '{{%product_images}}', 'product_id');
        $this->addForeignKey(
            'fk_product_images_product_id',
            '{{%product_images}}',
            'product_id',
            '{{%products}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
    }

    public function safeDown(): void
    {
        $this->dropForeignKey('fk_product_images_product_id', '{{%product_images}}');
        $this->dropTable('{{%product_images}}');
    }
}
