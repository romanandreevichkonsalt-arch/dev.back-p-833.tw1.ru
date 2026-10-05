<?php

use yii\db\Migration;

class m260831_120000_catalog_import_run_progress extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn('{{%catalog_import_runs}}', 'type', $this->string(32)->notNull()->defaultValue('fabric')->after('id'));
        $this->addColumn('{{%catalog_import_runs}}', 'file_path', $this->string(512)->null()->after('sheet'));
        $this->addColumn('{{%catalog_import_runs}}', 'options_json', $this->text()->null()->after('file_path'));
        $this->addColumn('{{%catalog_import_runs}}', 'total_rows', $this->integer()->notNull()->defaultValue(0)->after('options_json'));
        $this->addColumn('{{%catalog_import_runs}}', 'processed_rows', $this->integer()->notNull()->defaultValue(0)->after('total_rows'));
        $this->addColumn('{{%catalog_import_runs}}', 'resume_row_index', $this->integer()->notNull()->defaultValue(0)->after('processed_rows'));
        $this->addColumn('{{%catalog_import_runs}}', 'phase', $this->string(32)->notNull()->defaultValue('')->after('status'));
        $this->addColumn('{{%catalog_import_runs}}', 'phase_message', $this->string(255)->notNull()->defaultValue('')->after('phase'));
        $this->addColumn('{{%catalog_import_runs}}', 'started_at', $this->dateTime()->null()->after('created_at'));
        $this->addColumn('{{%catalog_import_runs}}', 'finished_at', $this->dateTime()->null()->after('started_at'));
        $this->addColumn('{{%catalog_import_runs}}', 'error_message', $this->text()->null()->after('finished_at'));
    }

    public function safeDown(): void
    {
        foreach ([
            'error_message',
            'finished_at',
            'started_at',
            'phase_message',
            'phase',
            'resume_row_index',
            'processed_rows',
            'total_rows',
            'options_json',
            'file_path',
            'type',
        ] as $column) {
            $this->dropColumn('{{%catalog_import_runs}}', $column);
        }
    }
}
