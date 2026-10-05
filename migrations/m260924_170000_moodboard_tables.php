<?php

use yii\db\Migration;

class m260924_170000_moodboard_tables extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%moodboard_object_types}}', [
            'code' => $this->string(32)->notNull(),
            'label' => $this->string(128)->notNull(),
            'ref_table' => $this->string(64)->notNull(),
            'schema_version' => $this->integer()->notNull()->defaultValue(1),
            'is_active' => $this->boolean()->notNull()->defaultValue(true),
            'created_at' => $this->dateTime()->notNull(),
        ]);
        $this->addPrimaryKey('pk_moodboard_object_types', '{{%moodboard_object_types}}', 'code');

        $now = date('Y-m-d H:i:s');
        $this->batchInsert('{{%moodboard_object_types}}', ['code', 'label', 'ref_table', 'schema_version', 'is_active', 'created_at'], [
            ['model', 'Модель', 'catalog_models', 1, true, $now],
            ['fabric', 'Ткань', 'catalog_fabric_collection_colors', 1, true, $now],
            ['surface_material', 'Материал', 'catalog_surface_materials', 1, true, $now],
            ['product', 'Товар (SKU)', 'catalog_products', 1, true, $now],
        ]);

        $this->createTable('{{%moodboards}}', [
            'id' => $this->primaryKey(),
            'public_id' => $this->string(64)->notNull(),
            'author_user_id' => $this->integer()->notNull(),
            'title' => $this->string(255)->notNull(),
            'cover_media_id' => $this->integer()->null(),
            'canvas_width' => $this->integer()->null(),
            'canvas_height' => $this->integer()->null(),
            'status' => $this->string(16)->notNull()->defaultValue('draft'),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex('uidx_moodboards_public_id', '{{%moodboards}}', 'public_id', true);
        $this->createIndex('idx_moodboards_author_updated', '{{%moodboards}}', ['author_user_id', 'updated_at']);

        $this->addForeignKey(
            'fk_moodboards_author_user_id',
            '{{%moodboards}}',
            'author_user_id',
            '{{%users}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
        $this->addForeignKey(
            'fk_moodboards_cover_media_id',
            '{{%moodboards}}',
            'cover_media_id',
            '{{%media_files}}',
            'id',
            'SET NULL',
            'CASCADE'
        );

        $this->createTable('{{%moodboard_items}}', [
            'id' => $this->primaryKey(),
            'moodboard_id' => $this->integer()->notNull(),
            'object_type' => $this->string(32)->notNull(),
            'ref_id' => $this->integer()->notNull(),
            'ref_slug' => $this->string(128)->notNull(),
            'width' => $this->decimal(12, 4)->notNull(),
            'height' => $this->decimal(12, 4)->notNull(),
            'x' => $this->decimal(12, 4)->notNull(),
            'y' => $this->decimal(12, 4)->notNull(),
            'z_index' => $this->integer()->notNull()->defaultValue(0),
            'rotation' => $this->decimal(8, 4)->notNull()->defaultValue(0),
            'payload_json' => $this->text()->null(),
            'sort_order' => $this->integer()->notNull()->defaultValue(0),
            'created_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex('idx_moodboard_items_board', '{{%moodboard_items}}', 'moodboard_id');
        $this->createIndex('idx_moodboard_items_ref', '{{%moodboard_items}}', ['object_type', 'ref_id']);

        $this->addForeignKey(
            'fk_moodboard_items_moodboard_id',
            '{{%moodboard_items}}',
            'moodboard_id',
            '{{%moodboards}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
        $this->addForeignKey(
            'fk_moodboard_items_object_type',
            '{{%moodboard_items}}',
            'object_type',
            '{{%moodboard_object_types}}',
            'code',
            'RESTRICT',
            'CASCADE'
        );

        $this->createTable('{{%moodboard_comments}}', [
            'id' => $this->primaryKey(),
            'moodboard_id' => $this->integer()->notNull(),
            'text' => $this->text()->notNull(),
            'x' => $this->decimal(12, 4)->notNull(),
            'y' => $this->decimal(12, 4)->notNull(),
            'author_user_id' => $this->integer()->null(),
            'created_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex('idx_moodboard_comments_board', '{{%moodboard_comments}}', 'moodboard_id');

        $this->addForeignKey(
            'fk_moodboard_comments_moodboard_id',
            '{{%moodboard_comments}}',
            'moodboard_id',
            '{{%moodboards}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
        $this->addForeignKey(
            'fk_moodboard_comments_author_user_id',
            '{{%moodboard_comments}}',
            'author_user_id',
            '{{%users}}',
            'id',
            'SET NULL',
            'CASCADE'
        );

        $folderExists = (new \yii\db\Query())
            ->from('{{%media_folders}}')
            ->where(['slug' => 'moodboards'])
            ->exists($this->db);
        if (!$folderExists) {
            $this->insert('{{%media_folders}}', [
                'slug' => 'moodboards',
                'label' => 'Мудборды',
                'sort_order' => 50,
            ]);
        }
    }

    public function safeDown(): void
    {
        $this->dropForeignKey('fk_moodboard_comments_author_user_id', '{{%moodboard_comments}}');
        $this->dropForeignKey('fk_moodboard_comments_moodboard_id', '{{%moodboard_comments}}');
        $this->dropTable('{{%moodboard_comments}}');

        $this->dropForeignKey('fk_moodboard_items_object_type', '{{%moodboard_items}}');
        $this->dropForeignKey('fk_moodboard_items_moodboard_id', '{{%moodboard_items}}');
        $this->dropTable('{{%moodboard_items}}');

        $this->dropForeignKey('fk_moodboards_cover_media_id', '{{%moodboards}}');
        $this->dropForeignKey('fk_moodboards_author_user_id', '{{%moodboards}}');
        $this->dropTable('{{%moodboards}}');

        $this->dropTable('{{%moodboard_object_types}}');
    }
}
