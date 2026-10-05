<?php

use yii\db\Migration;

class m260929_210000_lead_attachment extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn('{{%leads}}', 'attachment_path', $this->string(512)->null()->after('resume_name'));
        $this->addColumn('{{%leads}}', 'attachment_original_name', $this->string(255)->null()->after('attachment_path'));
    }

    public function safeDown(): void
    {
        $this->dropColumn('{{%leads}}', 'attachment_original_name');
        $this->dropColumn('{{%leads}}', 'attachment_path');
    }
}
