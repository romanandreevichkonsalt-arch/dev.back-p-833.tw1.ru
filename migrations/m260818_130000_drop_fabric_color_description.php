<?php

use yii\db\Migration;

class m260818_130000_drop_fabric_color_description extends Migration
{
    public function safeUp(): void
    {
        if ($this->columnExists('{{%catalog_fabric_collection_colors}}', 'description')) {
            $this->dropColumn('{{%catalog_fabric_collection_colors}}', 'description');
        }
    }

    public function safeDown(): void
    {
    }

    private function columnExists(string $table, string $column): bool
    {
        $schema = $this->db->schema->getTableSchema($table, true);

        return $schema !== null && isset($schema->columns[$column]);
    }
}
