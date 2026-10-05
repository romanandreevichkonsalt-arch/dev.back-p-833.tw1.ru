<?php

use yii\db\Migration;

class m260811_300000_create_journal_articles_table extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%journal_articles}}', [
            'id' => $this->primaryKey(),
            'slug' => $this->string(128)->notNull()->unique(),
            'category_id' => $this->string(32)->notNull(),
            'title' => $this->string(255)->notNull(),
            'date' => $this->string(16)->notNull()->defaultValue(''),
            'reading_time' => $this->string(32)->notNull()->defaultValue(''),
            'excerpt' => $this->text()->notNull(),
            'tall' => $this->boolean()->notNull()->defaultValue(false),
            'image_src' => $this->string(1024)->notNull()->defaultValue(''),
            'image_alt' => $this->string(255)->notNull()->defaultValue(''),
            'image_position' => $this->string(64)->notNull()->defaultValue(''),
            'seo_title' => $this->string(255)->notNull()->defaultValue(''),
            'seo_description' => $this->text()->notNull(),
            'blocks' => $this->text()->notNull(),
            'sort_order' => $this->integer()->notNull()->defaultValue(0),
            'is_active' => $this->boolean()->notNull()->defaultValue(true),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);

        $this->createIndex('idx_journal_articles_category', '{{%journal_articles}}', ['category_id', 'sort_order', 'id']);
        $this->createIndex('idx_journal_articles_active', '{{%journal_articles}}', ['is_active']);
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%journal_articles}}');
    }
}
