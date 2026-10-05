<?php

use yii\db\Migration;

class m260918_170000_dealer_cashback_admin_settings extends Migration
{
    public function safeUp(): void
    {
        if (!$this->db->getTableSchema('{{%dealer_program_settings}}', true)) {
            $this->createTable('{{%dealer_program_settings}}', [
                'id' => $this->primaryKey(),
                'cashback_default_expiry_days' => $this->integer()->notNull()->defaultValue(90),
                'updated_at' => $this->dateTime()->notNull(),
            ]);
            $this->insert('{{%dealer_program_settings}}', [
                'cashback_default_expiry_days' => 90,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        if (!$this->db->getTableSchema('{{%dealer_profiles}}', true)->getColumn('cashback_expiry_days')) {
            $this->addColumn(
                '{{%dealer_profiles}}',
                'cashback_expiry_days',
                $this->integer()->null()->after('personal_discount_percent')
            );
        }
    }

    public function safeDown(): void
    {
        if ($this->db->getTableSchema('{{%dealer_profiles}}', true)->getColumn('cashback_expiry_days')) {
            $this->dropColumn('{{%dealer_profiles}}', 'cashback_expiry_days');
        }

        if ($this->db->getTableSchema('{{%dealer_program_settings}}', true)) {
            $this->dropTable('{{%dealer_program_settings}}');
        }
    }
}
