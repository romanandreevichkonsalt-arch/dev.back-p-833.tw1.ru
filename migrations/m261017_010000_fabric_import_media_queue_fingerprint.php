<?php

use yii\db\Migration;

class m261017_010000_fabric_import_media_queue_fingerprint extends Migration
{
    public function safeUp(): void
    {
        $queue = '{{%catalog_import_media_queue}}';
        if ($this->db->schema->getTableSchema($queue, true) === null) {
            return;
        }

        $schema = $this->db->schema->getTableSchema($queue, true);
        if ($schema->getColumn('url_fingerprint') === null) {
            $this->addColumn($queue, 'url_fingerprint', $this->char(64)->null()->after('source_url'));
            $this->createIndex(
                'idx_catalog_import_media_queue_fingerprint_status',
                $queue,
                ['url_fingerprint', 'status']
            );
        }

        $colors = '{{%catalog_fabric_collection_colors}}';
        if ($this->db->schema->getTableSchema($colors, true) === null) {
            return;
        }

        $colorSchema = $this->db->schema->getTableSchema($colors, true);
        if ($colorSchema->getColumn('import_media_pending') === null) {
            $this->addColumn(
                $colors,
                'import_media_pending',
                $this->boolean()->notNull()->defaultValue(false)->after('import_media_fingerprint')
            );
        }
    }

    public function safeDown(): void
    {
        $queue = '{{%catalog_import_media_queue}}';
        if ($this->db->schema->getTableSchema($queue, true)?->getColumn('url_fingerprint') !== null) {
            $this->dropIndex('idx_catalog_import_media_queue_fingerprint_status', $queue);
            $this->dropColumn($queue, 'url_fingerprint');
        }

        $colors = '{{%catalog_fabric_collection_colors}}';
        if ($this->db->schema->getTableSchema($colors, true)?->getColumn('import_media_pending') !== null) {
            $this->dropColumn($colors, 'import_media_pending');
        }
    }
}
