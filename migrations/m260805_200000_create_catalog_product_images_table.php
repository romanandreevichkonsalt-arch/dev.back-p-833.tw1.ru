<?php

use yii\db\Migration;

class m260805_200000_create_catalog_product_images_table extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%catalog_product_images}}', [
            'id' => $this->primaryKey(),
            'product_id' => $this->integer()->notNull(),
            'media_file_id' => $this->integer()->notNull(),
            'sort_order' => $this->integer()->notNull()->defaultValue(0),
            'created_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex('ux_catalog_product_images_product_media', '{{%catalog_product_images}}', ['product_id', 'media_file_id'], true);
        $this->createIndex('idx_catalog_product_images_product_id', '{{%catalog_product_images}}', 'product_id');
        $this->addForeignKey(
            'fk_catalog_product_images_product_id',
            '{{%catalog_product_images}}',
            'product_id',
            '{{%catalog_products}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
        $this->addForeignKey(
            'fk_catalog_product_images_media_file_id',
            '{{%catalog_product_images}}',
            'media_file_id',
            '{{%media_files}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
    }

    public function safeDown(): void
    {
        $this->dropForeignKey('fk_catalog_product_images_media_file_id', '{{%catalog_product_images}}');
        $this->dropForeignKey('fk_catalog_product_images_product_id', '{{%catalog_product_images}}');
        $this->dropTable('{{%catalog_product_images}}');
    }
}
