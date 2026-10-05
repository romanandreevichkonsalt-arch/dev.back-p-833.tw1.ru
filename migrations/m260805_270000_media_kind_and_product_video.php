<?php

use yii\db\Migration;

class m260805_270000_media_kind_and_product_video extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn('{{%media_files}}', 'kind', $this->string(16)->notNull()->defaultValue('image')->after('path'));
        $this->createIndex('idx_media_files_kind', '{{%media_files}}', 'kind');
        $this->update('{{%media_files}}', ['kind' => 'image']);

        $this->addColumn('{{%catalog_products}}', 'video_id', $this->integer()->null()->after('image_id'));
        $this->addForeignKey(
            'fk_catalog_products_video_id',
            '{{%catalog_products}}',
            'video_id',
            '{{%media_files}}',
            'id',
            'SET NULL',
            'CASCADE'
        );
    }

    public function safeDown(): void
    {
        $this->dropForeignKey('fk_catalog_products_video_id', '{{%catalog_products}}');
        $this->dropColumn('{{%catalog_products}}', 'video_id');
        $this->dropIndex('idx_media_files_kind', '{{%media_files}}');
        $this->dropColumn('{{%media_files}}', 'kind');
    }
}
