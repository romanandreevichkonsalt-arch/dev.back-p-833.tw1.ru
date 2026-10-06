<?php

use yii\db\Migration;

class m261006_120000_fabric_color_description extends Migration
{
    public function safeUp(): void
    {
        if (!$this->columnExists('{{%catalog_fabric_collection_colors}}', 'description')) {
            $this->addColumn(
                '{{%catalog_fabric_collection_colors}}',
                'description',
                $this->text()->null()->after('import_comment')
            );
        }
    }

    public function safeDown(): void
    {
        if ($this->columnExists('{{%catalog_fabric_collection_colors}}', 'description')) {
            $this->dropColumn('{{%catalog_fabric_collection_colors}}', 'description');
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        $schema = $this->db->schema->getTableSchema($table, true);

        return $schema !== null && isset($schema->columns[$column]);
    }
}
