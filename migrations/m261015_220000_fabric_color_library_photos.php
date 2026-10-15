<?php

use yii\db\Migration;

class m261015_220000_fabric_color_library_photos extends Migration
{
    public function safeUp(): void
    {
        if ($this->db->schema->getTableSchema('{{%catalog_fabric_color_library_photos}}', true) !== null) {
            return;
        }

        $this->createTable('{{%catalog_fabric_color_library_photos}}', [
            'id' => $this->primaryKey(),
            'fabric_color_id' => $this->integer()->notNull(),
            'media_file_id' => $this->integer()->notNull(),
            'sort_order' => $this->integer()->notNull()->defaultValue(0),
            'created_at' => $this->dateTime()->notNull(),
        ]);

        $this->createIndex(
            'ux_catalog_fabric_color_library_photos_color_media',
            '{{%catalog_fabric_color_library_photos}}',
            ['fabric_color_id', 'media_file_id'],
            true
        );

        $this->addForeignKey(
            'fk_catalog_fabric_color_library_photos_fabric_color_id',
            '{{%catalog_fabric_color_library_photos}}',
            'fabric_color_id',
            '{{%catalog_fabric_collection_colors}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->addForeignKey(
            'fk_catalog_fabric_color_library_photos_media_file_id',
            '{{%catalog_fabric_color_library_photos}}',
            'media_file_id',
            '{{%media_files}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
    }

    public function safeDown(): void
    {
        if ($this->db->schema->getTableSchema('{{%catalog_fabric_color_library_photos}}', true) === null) {
            return;
        }

        $this->dropForeignKey(
            'fk_catalog_fabric_color_library_photos_media_file_id',
            '{{%catalog_fabric_color_library_photos}}'
        );
        $this->dropForeignKey(
            'fk_catalog_fabric_color_library_photos_fabric_color_id',
            '{{%catalog_fabric_color_library_photos}}'
        );
        $this->dropTable('{{%catalog_fabric_color_library_photos}}');
    }
}
