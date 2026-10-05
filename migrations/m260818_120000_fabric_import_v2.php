<?php

use yii\db\Migration;

class m260818_120000_fabric_import_v2 extends Migration
{
    public function safeUp(): void
    {
        if ($this->columnExists('{{%catalog_fabric_collection_colors}}', 'description')) {
            $this->dropColumn('{{%catalog_fabric_collection_colors}}', 'description');
        }

        if ($this->columnExists('{{%catalog_fabric_collection_colors}}', 'design_code')) {
            $this->alterColumn('{{%catalog_fabric_collection_colors}}', 'design_code', $this->string(255)->notNull());
        }

        if ($this->foreignKeyExists('fk_catalog_fabric_collections_cert_media_id')) {
            $this->dropForeignKey('fk_catalog_fabric_collections_cert_media_id', '{{%catalog_fabric_collections}}');
        }
        if ($this->columnExists('{{%catalog_fabric_collections}}', 'source_cert_url')) {
            $this->dropColumn('{{%catalog_fabric_collections}}', 'source_cert_url');
        }
        if ($this->columnExists('{{%catalog_fabric_collections}}', 'cert_media_id')) {
            $this->dropColumn('{{%catalog_fabric_collections}}', 'cert_media_id');
        }

        if ($this->foreignKeyExists('fk_catalog_fabric_collection_colors_cert_media_id')) {
            $this->dropForeignKey('fk_catalog_fabric_collection_colors_cert_media_id', '{{%catalog_fabric_collection_colors}}');
        }
        if ($this->columnExists('{{%catalog_fabric_collection_colors}}', 'cert_media_id')) {
            $this->dropColumn('{{%catalog_fabric_collection_colors}}', 'cert_media_id');
        }
    }

    public function safeDown(): void
    {
        if ($this->columnExists('{{%catalog_fabric_collection_colors}}', 'description')) {
            $this->dropColumn('{{%catalog_fabric_collection_colors}}', 'description');
        }

        if (!$this->columnExists('{{%catalog_fabric_collections}}', 'cert_media_id')) {
            $this->addColumn('{{%catalog_fabric_collections}}', 'cert_media_id', $this->integer()->null());
        }
        if (!$this->columnExists('{{%catalog_fabric_collections}}', 'source_cert_url')) {
            $this->addColumn('{{%catalog_fabric_collections}}', 'source_cert_url', $this->string(512)->null());
        }
        if (!$this->foreignKeyExists('fk_catalog_fabric_collections_cert_media_id')) {
            $this->addForeignKey(
                'fk_catalog_fabric_collections_cert_media_id',
                '{{%catalog_fabric_collections}}',
                'cert_media_id',
                '{{%media_files}}',
                'id',
                'SET NULL',
                'CASCADE'
            );
        }

        if (!$this->columnExists('{{%catalog_fabric_collection_colors}}', 'cert_media_id')) {
            $this->addColumn('{{%catalog_fabric_collection_colors}}', 'cert_media_id', $this->integer()->null());
        }
        if (!$this->foreignKeyExists('fk_catalog_fabric_collection_colors_cert_media_id')) {
            $this->addForeignKey(
                'fk_catalog_fabric_collection_colors_cert_media_id',
                '{{%catalog_fabric_collection_colors}}',
                'cert_media_id',
                '{{%media_files}}',
                'id',
                'SET NULL',
                'CASCADE'
            );
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        $schema = $this->db->schema->getTableSchema($table, true);

        return $schema !== null && isset($schema->columns[$column]);
    }

    private function foreignKeyExists(string $name): bool
    {
        foreach (['catalog_fabric_collections', 'catalog_fabric_collection_colors'] as $tableName) {
            $rawTable = $this->db->schema->getRawTableName('{{%' . $tableName . '}}');
            $rows = $this->db->createCommand(
                "SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
                 WHERE CONSTRAINT_SCHEMA = DATABASE()
                   AND TABLE_NAME = :table
                   AND CONSTRAINT_NAME = :name
                   AND CONSTRAINT_TYPE = 'FOREIGN KEY'",
                [':table' => $rawTable, ':name' => $name]
            )->queryAll();

            if ($rows !== []) {
                return true;
            }
        }

        return false;
    }
}
