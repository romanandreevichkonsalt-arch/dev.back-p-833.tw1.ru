<?php

use yii\db\Migration;

class m260827_120000_catalog_model_import_v2_fields extends Migration
{
    public function safeUp(): void
    {
        foreach (['{{%catalog_models}}', '{{%catalog_products}}'] as $table) {
            if ($this->db->getTableSchema($table)->getColumn('clearance') !== null) {
                $this->renameColumn($table, 'clearance', 'leg_height');
            }
        }

        $this->addColumn('{{%catalog_models}}', 'frame_spec', $this->text()->null()->after('frame'));
        $this->addColumn('{{%catalog_models}}', 'mechanism', $this->text()->null()->after('frame_spec'));
        $this->addColumn('{{%catalog_models}}', 'filling_spec', $this->text()->null()->after('filling'));
        $this->addColumn('{{%catalog_models}}', 'additional', $this->text()->null()->after('filling_spec'));

        $this->addColumn('{{%catalog_products}}', 'frame_spec', $this->text()->null()->after('frame'));
        $this->addColumn('{{%catalog_products}}', 'mechanism', $this->text()->null()->after('frame_spec'));
        $this->addColumn('{{%catalog_products}}', 'filling_spec', $this->text()->null()->after('filling'));
        $this->addColumn('{{%catalog_products}}', 'additional', $this->text()->null()->after('filling_spec'));
    }

    public function safeDown(): void
    {
        $this->dropColumn('{{%catalog_products}}', 'additional');
        $this->dropColumn('{{%catalog_products}}', 'filling_spec');
        $this->dropColumn('{{%catalog_products}}', 'mechanism');
        $this->dropColumn('{{%catalog_products}}', 'frame_spec');

        $this->dropColumn('{{%catalog_models}}', 'additional');
        $this->dropColumn('{{%catalog_models}}', 'filling_spec');
        $this->dropColumn('{{%catalog_models}}', 'mechanism');
        $this->dropColumn('{{%catalog_models}}', 'frame_spec');

        foreach (['{{%catalog_models}}', '{{%catalog_products}}'] as $table) {
            if ($this->db->getTableSchema($table)->getColumn('leg_height') !== null) {
                $this->renameColumn($table, 'leg_height', 'clearance');
            }
        }
    }
}
