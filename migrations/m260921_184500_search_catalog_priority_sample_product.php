<?php

use yii\db\Migration;

class m260921_184500_search_catalog_priority_sample_product extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn(
            '{{%search_catalog_priority_models}}',
            'sample_catalog_product_id',
            $this->integer()->null()->after('catalog_model_id')
        );

        $this->createIndex(
            'idx_search_catalog_priority_sample_product',
            '{{%search_catalog_priority_models}}',
            'sample_catalog_product_id'
        );

        $this->addForeignKey(
            'fk_search_catalog_priority_sample_product',
            '{{%search_catalog_priority_models}}',
            'sample_catalog_product_id',
            '{{%catalog_products}}',
            'id',
            'SET NULL',
            'CASCADE'
        );
    }

    public function safeDown(): void
    {
        $this->dropForeignKey('fk_search_catalog_priority_sample_product', '{{%search_catalog_priority_models}}');
        $this->dropIndex('idx_search_catalog_priority_sample_product', '{{%search_catalog_priority_models}}');
        $this->dropColumn('{{%search_catalog_priority_models}}', 'sample_catalog_product_id');
    }
}
