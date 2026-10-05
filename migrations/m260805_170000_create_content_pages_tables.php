<?php

use yii\db\Migration;

class m260805_170000_create_content_pages_tables extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%content_pages}}', [
            'id' => $this->primaryKey(),
            'slug' => $this->string(64)->notNull()->unique(),
            'title' => $this->string(255)->notNull(),
            'is_active' => $this->boolean()->notNull()->defaultValue(true),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);

        $this->createTable('{{%content_blocks}}', [
            'id' => $this->primaryKey(),
            'page_id' => $this->integer()->notNull(),
            'block_key' => $this->string(64)->notNull(),
            'block_type' => $this->string(64)->notNull(),
            'data' => $this->text()->notNull(),
            'sort_order' => $this->integer()->notNull()->defaultValue(0),
            'is_active' => $this->boolean()->notNull()->defaultValue(true),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex('ux_content_blocks_page_key', '{{%content_blocks}}', ['page_id', 'block_key'], true);
        $this->createIndex('idx_content_blocks_page_id', '{{%content_blocks}}', 'page_id');
        $this->addForeignKey(
            'fk_content_blocks_page_id',
            '{{%content_blocks}}',
            'page_id',
            '{{%content_pages}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
    }

    public function safeDown(): void
    {
        $this->dropForeignKey('fk_content_blocks_page_id', '{{%content_blocks}}');
        $this->dropTable('{{%content_blocks}}');
        $this->dropTable('{{%content_pages}}');
    }
}
