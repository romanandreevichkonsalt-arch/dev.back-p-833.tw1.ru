<?php

use yii\db\Migration;

class m260616_100000_create_categories_table extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%categories}}', [
            'id' => $this->primaryKey(),
            'parent_id' => $this->integer()->null(),
            'name' => $this->string(255)->notNull(),
            'slug' => $this->string(255)->notNull()->unique(),
            'description' => $this->text()->null(),
            'seo_title' => $this->string(255)->null(),
            'seo_description' => $this->string(512)->null(),
            'seo_h1' => $this->string(255)->null(),
            'is_active' => $this->boolean()->notNull()->defaultValue(true),
            'sort_order' => $this->integer()->notNull()->defaultValue(0),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);

        $this->createIndex('idx_categories_parent_id', '{{%categories}}', 'parent_id');
        $this->createIndex('idx_categories_is_active', '{{%categories}}', 'is_active');
        $this->createIndex('idx_categories_sort_order', '{{%categories}}', 'sort_order');

        $this->addForeignKey(
            'fk_categories_parent_id',
            '{{%categories}}',
            'parent_id',
            '{{%categories}}',
            'id',
            'RESTRICT',
            'CASCADE'
        );
    }

    public function safeDown(): void
    {
        $this->dropForeignKey('fk_categories_parent_id', '{{%categories}}');
        $this->dropTable('{{%categories}}');
    }
}
