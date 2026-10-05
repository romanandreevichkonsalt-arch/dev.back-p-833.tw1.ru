<?php

use yii\db\Migration;

class m260921_153000_search_catalog_priority_models extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%search_catalog_priority_models}}', [
            'id' => $this->primaryKey(),
            'direction_id' => $this->integer()->notNull(),
            'catalog_model_id' => $this->integer()->notNull(),
            'sort_order' => $this->integer()->notNull()->defaultValue(0),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);

        $this->createIndex(
            'idx_search_catalog_priority_direction_model',
            '{{%search_catalog_priority_models}}',
            ['direction_id', 'catalog_model_id'],
            true
        );
        $this->createIndex(
            'idx_search_catalog_priority_direction_sort',
            '{{%search_catalog_priority_models}}',
            ['direction_id', 'sort_order', 'id']
        );

        $this->addForeignKey(
            'fk_search_catalog_priority_direction',
            '{{%search_catalog_priority_models}}',
            'direction_id',
            '{{%catalog_directions}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
        $this->addForeignKey(
            'fk_search_catalog_priority_model',
            '{{%search_catalog_priority_models}}',
            'catalog_model_id',
            '{{%catalog_models}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
    }

    public function safeDown(): void
    {
        $this->dropForeignKey('fk_search_catalog_priority_model', '{{%search_catalog_priority_models}}');
        $this->dropForeignKey('fk_search_catalog_priority_direction', '{{%search_catalog_priority_models}}');
        $this->dropTable('{{%search_catalog_priority_models}}');
    }
}
