<?php

use yii\db\Migration;

class m260805_180000_create_search_tables extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%search_frequent_queries}}', [
            'id' => $this->primaryKey(),
            'query' => $this->string(255)->notNull(),
            'sort_order' => $this->integer()->notNull()->defaultValue(0),
            'is_active' => $this->boolean()->notNull()->defaultValue(true),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);

        $this->createTable('{{%search_categories}}', [
            'id' => $this->primaryKey(),
            'slug' => $this->string(64)->notNull()->unique(),
            'label' => $this->string(255)->notNull(),
            'icon' => $this->string(64)->notNull(),
            'href' => $this->string(512)->notNull(),
            'sort_order' => $this->integer()->notNull()->defaultValue(0),
            'is_active' => $this->boolean()->notNull()->defaultValue(true),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);

        $this->addColumn(
            '{{%catalog_products}}',
            'is_search_recommended',
            $this->boolean()->notNull()->defaultValue(false)->after('is_active')
        );
        $this->createIndex('idx_catalog_products_is_search_recommended', '{{%catalog_products}}', 'is_search_recommended');
    }

    public function safeDown(): void
    {
        $this->dropIndex('idx_catalog_products_is_search_recommended', '{{%catalog_products}}');
        $this->dropColumn('{{%catalog_products}}', 'is_search_recommended');
        $this->dropTable('{{%search_categories}}');
        $this->dropTable('{{%search_frequent_queries}}');
    }
}
