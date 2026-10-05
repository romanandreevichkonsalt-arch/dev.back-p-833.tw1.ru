<?php

use yii\db\Migration;

class m260805_150000_extend_leads_table extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn('{{%leads}}', 'status', $this->string(32)->notNull()->defaultValue('new')->after('consent'));
        $this->addColumn('{{%leads}}', 'manager_comment', $this->text()->null()->after('status'));
        $this->addColumn('{{%leads}}', 'assigned_to', $this->integer()->null()->after('manager_comment'));
        $this->addColumn('{{%leads}}', 'processed_at', $this->dateTime()->null()->after('assigned_to'));

        $this->createIndex('idx_leads_status', '{{%leads}}', 'status');
        $this->addForeignKey(
            'fk_leads_assigned_to',
            '{{%leads}}',
            'assigned_to',
            '{{%admin_users}}',
            'id',
            'SET NULL',
            'CASCADE'
        );
    }

    public function safeDown(): void
    {
        $this->dropForeignKey('fk_leads_assigned_to', '{{%leads}}');
        $this->dropColumn('{{%leads}}', 'processed_at');
        $this->dropColumn('{{%leads}}', 'assigned_to');
        $this->dropColumn('{{%leads}}', 'manager_comment');
        $this->dropColumn('{{%leads}}', 'status');
    }
}
