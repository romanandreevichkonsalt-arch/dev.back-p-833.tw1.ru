<?php

use yii\db\Migration;
use yii\db\Query;

class m260805_260000_catalog_collection_images extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%catalog_collection_images}}', [
            'id' => $this->primaryKey(),
            'collection_id' => $this->integer()->notNull(),
            'media_file_id' => $this->integer()->notNull(),
            'sort_order' => $this->integer()->notNull()->defaultValue(0),
            'created_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex(
            'ux_catalog_collection_images_collection_media',
            '{{%catalog_collection_images}}',
            ['collection_id', 'media_file_id'],
            true
        );
        $this->createIndex('idx_catalog_collection_images_collection_id', '{{%catalog_collection_images}}', 'collection_id');
        $this->addForeignKey(
            'fk_catalog_collection_images_collection_id',
            '{{%catalog_collection_images}}',
            'collection_id',
            '{{%catalog_collections}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
        $this->addForeignKey(
            'fk_catalog_collection_images_media_file_id',
            '{{%catalog_collection_images}}',
            'media_file_id',
            '{{%media_files}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $now = date('Y-m-d H:i:s');
        foreach ((new Query())
            ->from('{{%catalog_collections}}')
            ->where(['not', ['image_id' => null]])
            ->all() as $row) {
            $this->insert('{{%catalog_collection_images}}', [
                'collection_id' => (int)$row['id'],
                'media_file_id' => (int)$row['image_id'],
                'sort_order' => 0,
                'created_at' => $now,
            ]);
        }
    }

    public function safeDown(): void
    {
        $this->dropForeignKey('fk_catalog_collection_images_media_file_id', '{{%catalog_collection_images}}');
        $this->dropForeignKey('fk_catalog_collection_images_collection_id', '{{%catalog_collection_images}}');
        $this->dropTable('{{%catalog_collection_images}}');
    }
}
