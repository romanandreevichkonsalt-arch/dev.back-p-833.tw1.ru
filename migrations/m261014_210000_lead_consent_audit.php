<?php

use yii\db\Migration;

class m261014_210000_lead_consent_audit extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn('{{%leads}}', 'consent_given_at', $this->dateTime()->null()->after('consent'));
        $this->addColumn('{{%leads}}', 'consent_given_by_name', $this->string(255)->null()->after('consent_given_at'));
        $this->addColumn('{{%leads}}', 'consent_session_id', $this->string(64)->null()->after('consent_given_by_name'));

        $this->createIndex('idx_leads_consent_session_id', '{{%leads}}', 'consent_session_id');

        $this->execute(
            'UPDATE {{%leads}} SET consent_given_at = created_at, consent_given_by_name = name'
            . ' WHERE consent = 1 AND consent_given_at IS NULL'
        );
    }

    public function safeDown(): void
    {
        $this->dropIndex('idx_leads_consent_session_id', '{{%leads}}');
        $this->dropColumn('{{%leads}}', 'consent_session_id');
        $this->dropColumn('{{%leads}}', 'consent_given_by_name');
        $this->dropColumn('{{%leads}}', 'consent_given_at');
    }
}
