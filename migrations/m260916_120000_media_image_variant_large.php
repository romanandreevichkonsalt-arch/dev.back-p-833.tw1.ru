<?php

use yii\db\Migration;

class m260916_120000_media_image_variant_large extends Migration
{
    public function safeUp(): void
    {
        if (!$this->db->schema->getTableSchema('{{%media_files}}')->getColumn('path_large')) {
            $this->addColumn('{{%media_files}}', 'path_large', $this->string(512)->null()->after('path_medium'));
        }
    }

    public function safeDown(): void
    {
        if ($this->db->schema->getTableSchema('{{%media_files}}')->getColumn('path_large')) {
            $this->dropColumn('{{%media_files}}', 'path_large');
        }
    }
}
