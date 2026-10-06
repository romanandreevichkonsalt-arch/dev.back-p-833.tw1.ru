<?php

use yii\db\Migration;

class m261006_132000_catalog_import_run_mediumtext extends Migration
{
    public function safeUp(): void
    {
        if (!$this->db->schema->getTableSchema('{{%catalog_import_runs}}', true)) {
            return;
        }

        $this->execute('ALTER TABLE {{%catalog_import_runs}} MODIFY `stats_json` MEDIUMTEXT NULL');
        $this->execute('ALTER TABLE {{%catalog_import_runs}} MODIFY `error_message` MEDIUMTEXT NULL');
        $this->execute('ALTER TABLE {{%catalog_import_runs}} MODIFY `options_json` MEDIUMTEXT NULL');
    }

    public function safeDown(): void
    {
        if (!$this->db->schema->getTableSchema('{{%catalog_import_runs}}', true)) {
            return;
        }

        $this->execute('ALTER TABLE {{%catalog_import_runs}} MODIFY `stats_json` TEXT NULL');
        $this->execute('ALTER TABLE {{%catalog_import_runs}} MODIFY `error_message` TEXT NULL');
        $this->execute('ALTER TABLE {{%catalog_import_runs}} MODIFY `options_json` TEXT NULL');
    }
}
