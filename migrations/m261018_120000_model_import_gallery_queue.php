<?php

use yii\db\Migration;

class m261018_120000_model_import_gallery_queue extends Migration
{
    public function safeUp(): void
    {
        $images = '{{%catalog_model_images}}';
        if ($this->db->schema->getTableSchema($images, true) !== null
            && $this->db->schema->getTableSchema($images, true)->getColumn('source_url') === null
        ) {
            $this->addColumn($images, 'source_url', $this->string(512)->null()->after('sort_order'));
        }

        $dimensions = '{{%catalog_model_dimension_images}}';
        if ($this->db->schema->getTableSchema($dimensions, true) !== null
            && $this->db->schema->getTableSchema($dimensions, true)->getColumn('source_url') === null
        ) {
            $this->addColumn($dimensions, 'source_url', $this->string(512)->null()->after('sort_order'));
        }

        $queue = '{{%catalog_import_media_queue}}';
        if ($this->db->schema->getTableSchema($queue, true) !== null
            && $this->db->schema->getTableSchema($queue, true)->getColumn('slot_index') === null
        ) {
            $this->addColumn($queue, 'slot_index', $this->smallInteger()->null()->after('entity_id'));
        }
    }

    public function safeDown(): void
    {
        $queue = '{{%catalog_import_media_queue}}';
        if ($this->db->schema->getTableSchema($queue, true)?->getColumn('slot_index') !== null) {
            $this->dropColumn($queue, 'slot_index');
        }

        $dimensions = '{{%catalog_model_dimension_images}}';
        if ($this->db->schema->getTableSchema($dimensions, true)?->getColumn('source_url') !== null) {
            $this->dropColumn($dimensions, 'source_url');
        }

        $images = '{{%catalog_model_images}}';
        if ($this->db->schema->getTableSchema($images, true)?->getColumn('source_url') !== null) {
            $this->dropColumn($images, 'source_url');
        }
    }
}
