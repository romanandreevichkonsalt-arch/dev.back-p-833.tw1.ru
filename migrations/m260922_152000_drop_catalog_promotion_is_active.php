<?php

use yii\db\Migration;

class m260922_152000_drop_catalog_promotion_is_active extends Migration
{
    public function safeUp(): void
    {
        $schema = $this->db->schema->getTableSchema('{{%catalog_promotions}}', true);
        if ($schema === null) {
            return;
        }

        if (isset($schema->columns['is_active'])) {
            $this->dropIndex('idx_catalog_promotions_scope', '{{%catalog_promotions}}');
            $this->dropColumn('{{%catalog_promotions}}', 'is_active');
            $this->createIndex(
                'idx_catalog_promotions_scope',
                '{{%catalog_promotions}}',
                ['catalog_model_id', 'catalog_product_id', 'starts_at', 'ends_at'],
            );
        }
    }

    public function safeDown(): void
    {
        $schema = $this->db->schema->getTableSchema('{{%catalog_promotions}}', true);
        if ($schema === null || isset($schema->columns['is_active'])) {
            return;
        }

        $this->dropIndex('idx_catalog_promotions_scope', '{{%catalog_promotions}}');
        $this->addColumn(
            '{{%catalog_promotions}}',
            'is_active',
            $this->boolean()->notNull()->defaultValue(true)->after('title')
        );
        $this->createIndex(
            'idx_catalog_promotions_scope',
            '{{%catalog_promotions}}',
            ['is_active', 'catalog_model_id', 'catalog_product_id', 'starts_at', 'ends_at'],
        );
    }
}
