<?php

use yii\db\Migration;

class m260815_170000_fabric_collection_cert_media extends Migration
{
    public function safeUp(): void
    {
        if (!$this->columnExists('{{%catalog_fabric_collections}}', 'cert_media_id')) {
            $this->addColumn('{{%catalog_fabric_collections}}', 'cert_media_id', $this->integer()->null()->after('import_row_hash'));
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
    }

    public function safeDown(): void
    {
        if ($this->foreignKeyExists('fk_catalog_fabric_collections_cert_media_id')) {
            $this->dropForeignKey('fk_catalog_fabric_collections_cert_media_id', '{{%catalog_fabric_collections}}');
        }

        if ($this->columnExists('{{%catalog_fabric_collections}}', 'cert_media_id')) {
            $this->dropColumn('{{%catalog_fabric_collections}}', 'cert_media_id');
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        $schema = $this->db->schema->getTableSchema($table, true);

        return $schema !== null && isset($schema->columns[$column]);
    }

    private function foreignKeyExists(string $name): bool
    {
        $rawTable = $this->db->schema->getRawTableName('{{%catalog_fabric_collections}}');

        return $this->db->createCommand(
            "SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE()
               AND TABLE_NAME = :table
               AND CONSTRAINT_NAME = :name
               AND CONSTRAINT_TYPE = 'FOREIGN KEY'",
            [':table' => $rawTable, ':name' => $name]
        )->queryOne() !== false;
    }
}
