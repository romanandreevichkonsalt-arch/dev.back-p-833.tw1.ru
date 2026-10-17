<?php

use yii\db\Migration;

class m261016_220000_catalog_import_run_media_progress extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn('{{%catalog_import_runs}}', 'media_total', $this->integer()->notNull()->defaultValue(0)->after('processed_rows'));
        $this->addColumn('{{%catalog_import_runs}}', 'media_processed_rows', $this->integer()->notNull()->defaultValue(0)->after('media_total'));
    }

    public function safeDown(): void
    {
        $this->dropColumn('{{%catalog_import_runs}}', 'media_processed_rows');
        $this->dropColumn('{{%catalog_import_runs}}', 'media_total');
    }
}
