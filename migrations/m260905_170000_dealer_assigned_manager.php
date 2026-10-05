<?php

use yii\db\Migration;

class m260905_170000_dealer_assigned_manager extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn('{{%admin_users}}', 'phone', $this->string(20)->null()->after('email'));
        $this->addColumn('{{%admin_users}}', 'work_hours', $this->string(255)->null()->after('phone'));
        $this->addColumn('{{%admin_users}}', 'public_role', $this->string(255)->null()->after('work_hours'));

        $this->addColumn('{{%dealer_profiles}}', 'assigned_admin_id', $this->integer()->null()->after('created_by_admin_id'));
        $this->createIndex('idx_dealer_profiles_assigned_admin_id', '{{%dealer_profiles}}', 'assigned_admin_id');
        $this->addForeignKey(
            'fk_dealer_profiles_assigned_admin_id',
            '{{%dealer_profiles}}',
            'assigned_admin_id',
            '{{%admin_users}}',
            'id',
            'SET NULL',
            'CASCADE'
        );
    }

    public function safeDown(): bool
    {
        $this->dropForeignKey('fk_dealer_profiles_assigned_admin_id', '{{%dealer_profiles}}');
        $this->dropIndex('idx_dealer_profiles_assigned_admin_id', '{{%dealer_profiles}}');
        $this->dropColumn('{{%dealer_profiles}}', 'assigned_admin_id');
        $this->dropColumn('{{%admin_users}}', 'public_role');
        $this->dropColumn('{{%admin_users}}', 'work_hours');
        $this->dropColumn('{{%admin_users}}', 'phone');

        return true;
    }
}
