<?php

use yii\db\Migration;

/**
 * Индексы под SQL цепочки поиска (без смены логики match/order и без кэша).
 */
class m260928_190000_search_catalog_db_indexes extends Migration
{
    public function safeUp(): void
    {
        $this->createIndex(
            'idx_catalog_products_searchable_sort',
            '{{%catalog_products}}',
            ['is_active', 'is_custom', 'sort_order', 'id']
        );

        $this->createIndex(
            'idx_search_frequent_queries_active_sort',
            '{{%search_frequent_queries}}',
            ['is_active', 'sort_order', 'id']
        );

        $this->createIndex(
            'idx_search_categories_active_sort',
            '{{%search_categories}}',
            ['is_active', 'sort_order', 'id']
        );

        $this->createIndex(
            'idx_catalog_subcategories_active_slug',
            '{{%catalog_subcategories}}',
            ['is_active', 'slug']
        );

        $this->createIndex(
            'idx_catalog_subcategories_active_url_slug',
            '{{%catalog_subcategories}}',
            ['is_active', 'url_slug']
        );
    }

    public function safeDown(): void
    {
        $this->dropIndex('idx_catalog_subcategories_active_url_slug', '{{%catalog_subcategories}}');
        $this->dropIndex('idx_catalog_subcategories_active_slug', '{{%catalog_subcategories}}');
        $this->dropIndex('idx_search_categories_active_sort', '{{%search_categories}}');
        $this->dropIndex('idx_search_frequent_queries_active_sort', '{{%search_frequent_queries}}');
        $this->dropIndex('idx_catalog_products_searchable_sort', '{{%catalog_products}}');
    }
}
