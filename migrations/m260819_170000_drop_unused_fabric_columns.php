<?php

use yii\db\Migration;

class m260819_170000_drop_unused_fabric_columns extends Migration
{
    public function safeUp(): void
    {
        if ($this->columnExists('{{%catalog_fabric_collections}}', 'available_colors_note')) {
            $this->dropColumn('{{%catalog_fabric_collections}}', 'available_colors_note');
        }

        $linkColumns = [
            'design_label',
            'composition',
            'martindale',
            'properties',
            'roll_width_cm',
            'density_gsm',
        ];

        foreach ($linkColumns as $column) {
            if ($this->columnExists('{{%catalog_fabric_collection_colors}}', $column)) {
                $this->dropColumn('{{%catalog_fabric_collection_colors}}', $column);
            }
        }
    }

    public function safeDown(): bool
    {
        if (!$this->columnExists('{{%catalog_fabric_collections}}', 'available_colors_note')) {
            $this->addColumn('{{%catalog_fabric_collections}}', 'available_colors_note', $this->text()->null());
        }

        $linkColumns = [
            'design_label' => $this->string(255)->null(),
            'composition' => $this->text()->null(),
            'martindale' => $this->integer()->null(),
            'properties' => $this->text()->null(),
            'roll_width_cm' => $this->smallInteger()->null(),
            'density_gsm' => $this->smallInteger()->null(),
        ];

        foreach ($linkColumns as $column => $type) {
            if (!$this->columnExists('{{%catalog_fabric_collection_colors}}', $column)) {
                $this->addColumn('{{%catalog_fabric_collection_colors}}', $column, $type);
            }
        }

        return true;
    }

    private function columnExists(string $table, string $column): bool
    {
        $schema = $this->db->schema->getTableSchema($table, true);

        return $schema !== null && isset($schema->columns[$column]);
    }
}
