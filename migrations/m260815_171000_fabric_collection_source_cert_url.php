<?php

use yii\db\Migration;

class m260815_171000_fabric_collection_source_cert_url extends Migration
{
    public function safeUp(): void
    {
        if (!$this->columnExists('{{%catalog_fabric_collections}}', 'source_cert_url')) {
            $this->addColumn(
                '{{%catalog_fabric_collections}}',
                'source_cert_url',
                $this->string(512)->null()->after('cert_media_id')
            );
        }

        $this->execute(
            "UPDATE {{%catalog_fabric_collections}} fc
             INNER JOIN (
                 SELECT fabric_collection_id, MIN(id) AS link_id
                 FROM {{%catalog_fabric_collection_colors}}
                 WHERE cert_media_id IS NOT NULL
                 GROUP BY fabric_collection_id
             ) src ON src.fabric_collection_id = fc.id
             INNER JOIN {{%catalog_fabric_collection_colors}} l ON l.id = src.link_id
             SET fc.cert_media_id = l.cert_media_id
             WHERE fc.cert_media_id IS NULL"
        );
    }

    public function safeDown(): void
    {
        if ($this->columnExists('{{%catalog_fabric_collections}}', 'source_cert_url')) {
            $this->dropColumn('{{%catalog_fabric_collections}}', 'source_cert_url');
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        $schema = $this->db->schema->getTableSchema($table, true);

        return $schema !== null && isset($schema->columns[$column]);
    }
}
