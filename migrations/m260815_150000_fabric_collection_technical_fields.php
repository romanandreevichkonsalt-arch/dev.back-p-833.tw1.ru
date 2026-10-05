<?php

use yii\db\Migration;

class m260815_150000_fabric_collection_technical_fields extends Migration
{
    public function safeUp(): void
    {
        $columns = [
            'composition' => $this->text()->null(),
            'density_gsm' => $this->smallInteger()->null(),
            'roll_width_cm' => $this->smallInteger()->null(),
            'martindale' => $this->integer()->null(),
        ];

        foreach ($columns as $name => $type) {
            if (!$this->columnExists('{{%catalog_fabric_collections}}', $name)) {
                $this->addColumn('{{%catalog_fabric_collections}}', $name, $type);
            }
        }

        $this->execute(
            'UPDATE {{%catalog_fabric_collections}} fc
             INNER JOIN (
                 SELECT l.fabric_collection_id,
                        MIN(l.composition) AS composition,
                        MIN(l.density_gsm) AS density_gsm,
                        MIN(l.roll_width_cm) AS roll_width_cm,
                        MIN(l.martindale) AS martindale
                 FROM {{%catalog_fabric_collection_colors}} l
                 WHERE l.composition IS NOT NULL
                    OR l.density_gsm IS NOT NULL
                    OR l.roll_width_cm IS NOT NULL
                    OR l.martindale IS NOT NULL
                 GROUP BY l.fabric_collection_id
             ) src ON src.fabric_collection_id = fc.id
             SET fc.composition = COALESCE(fc.composition, src.composition),
                 fc.density_gsm = COALESCE(fc.density_gsm, src.density_gsm),
                 fc.roll_width_cm = COALESCE(fc.roll_width_cm, src.roll_width_cm),
                 fc.martindale = COALESCE(fc.martindale, src.martindale)'
        );
    }

    public function safeDown(): void
    {
        foreach (['martindale', 'roll_width_cm', 'density_gsm', 'composition'] as $column) {
            if ($this->columnExists('{{%catalog_fabric_collections}}', $column)) {
                $this->dropColumn('{{%catalog_fabric_collections}}', $column);
            }
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        $schema = $this->db->schema->getTableSchema($table, true);

        return $schema !== null && isset($schema->columns[$column]);
    }
}
