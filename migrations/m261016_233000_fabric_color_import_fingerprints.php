<?php

use yii\db\Migration;

class m261016_233000_fabric_color_import_fingerprints extends Migration
{
    public function safeUp(): void
    {
        $table = '{{%catalog_fabric_collection_colors}}';
        if (!$this->db->schema->getTableSchema($table, true)) {
            return;
        }

        if ($this->db->schema->getTableSchema($table, true)->getColumn('import_row_hash') === null) {
            $this->addColumn($table, 'import_row_hash', $this->string(64)->null()->after('source_photo_url'));
        }
        if ($this->db->schema->getTableSchema($table, true)->getColumn('import_media_fingerprint') === null) {
            $this->addColumn($table, 'import_media_fingerprint', $this->string(64)->null()->after('import_row_hash'));
        }
    }

    public function safeDown(): void
    {
        $table = '{{%catalog_fabric_collection_colors}}';
        if (!$this->db->schema->getTableSchema($table, true)) {
            return;
        }

        $schema = $this->db->schema->getTableSchema($table, true);
        if ($schema->getColumn('import_media_fingerprint') !== null) {
            $this->dropColumn($table, 'import_media_fingerprint');
        }
        if ($schema->getColumn('import_row_hash') !== null) {
            $this->dropColumn($table, 'import_row_hash');
        }
    }
}
