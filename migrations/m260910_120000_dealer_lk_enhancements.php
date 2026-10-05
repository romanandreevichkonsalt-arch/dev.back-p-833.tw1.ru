<?php

use yii\db\Migration;

class m260910_120000_dealer_lk_enhancements extends Migration
{
    public function safeUp(): void
    {
        if ($this->db->getTableSchema('{{%dealer_managers}}', true) === null) {
            $this->createTable('{{%dealer_managers}}', [
                'id' => $this->primaryKey(),
                'name' => $this->string(255)->notNull(),
                'phone' => $this->string(20)->null(),
                'email' => $this->string(255)->null(),
                'work_hours' => $this->string(255)->null(),
                'role_label' => $this->string(255)->null(),
                'is_active' => $this->boolean()->notNull()->defaultValue(true),
                'created_at' => $this->dateTime()->notNull(),
                'updated_at' => $this->dateTime()->notNull(),
            ]);
            $this->createIndex('idx_dealer_managers_is_active', '{{%dealer_managers}}', 'is_active');
        }

        if (!$this->db->getTableSchema('{{%dealer_profiles}}')->getColumn('personal_discount_percent')) {
            $this->addColumn(
                '{{%dealer_profiles}}',
                'personal_discount_percent',
                $this->decimal(5, 2)->null()->after('dealer_type')
            );
        }

        if (!$this->db->getTableSchema('{{%dealer_profiles}}')->getColumn('assigned_manager_id')) {
            $this->addColumn(
                '{{%dealer_profiles}}',
                'assigned_manager_id',
                $this->integer()->null()->after('created_by_admin_id')
            );
            $this->createIndex('idx_dealer_profiles_assigned_manager_id', '{{%dealer_profiles}}', 'assigned_manager_id');
            $this->addForeignKey(
                'fk_dealer_profiles_assigned_manager_id',
                '{{%dealer_profiles}}',
                'assigned_manager_id',
                '{{%dealer_managers}}',
                'id',
                'SET NULL',
                'CASCADE'
            );
        }

        $this->migrateAssignedAdminsToManagers();

        if ($this->db->getTableSchema('{{%dealer_profiles}}')->getColumn('assigned_admin_id')) {
            $this->dropForeignKey('fk_dealer_profiles_assigned_admin_id', '{{%dealer_profiles}}');
            $this->dropIndex('idx_dealer_profiles_assigned_admin_id', '{{%dealer_profiles}}');
            $this->dropColumn('{{%dealer_profiles}}', 'assigned_admin_id');
        }

        if ($this->db->getTableSchema('{{%dealer_price_lists}}', true) === null) {
            $this->createTable('{{%dealer_price_lists}}', [
                'id' => $this->primaryKey(),
                'scope' => $this->string(16)->notNull(),
                'dealer_user_id' => $this->integer()->null(),
                'media_file_id' => $this->integer()->notNull(),
                'label' => $this->string(255)->notNull(),
                'is_active' => $this->boolean()->notNull()->defaultValue(true),
                'uploaded_by_admin_id' => $this->integer()->null(),
                'created_at' => $this->dateTime()->notNull(),
                'updated_at' => $this->dateTime()->notNull(),
            ]);
            $this->createIndex('idx_dealer_price_lists_scope', '{{%dealer_price_lists}}', 'scope');
            $this->createIndex('idx_dealer_price_lists_dealer_user_id', '{{%dealer_price_lists}}', 'dealer_user_id');
            $this->createIndex('idx_dealer_price_lists_active', '{{%dealer_price_lists}}', ['scope', 'dealer_user_id', 'is_active']);
            $this->addForeignKey(
                'fk_dealer_price_lists_dealer_user_id',
                '{{%dealer_price_lists}}',
                'dealer_user_id',
                '{{%users}}',
                'id',
                'CASCADE',
                'CASCADE'
            );
            $this->addForeignKey(
                'fk_dealer_price_lists_media_file_id',
                '{{%dealer_price_lists}}',
                'media_file_id',
                '{{%media_files}}',
                'id',
                'RESTRICT',
                'CASCADE'
            );
            $this->addForeignKey(
                'fk_dealer_price_lists_uploaded_by',
                '{{%dealer_price_lists}}',
                'uploaded_by_admin_id',
                '{{%admin_users}}',
                'id',
                'SET NULL',
                'CASCADE'
            );
        }

        $orderItems = $this->db->getTableSchema('{{%order_items}}');
        if ($orderItems !== null && $orderItems->getColumn('retail_unit_price') === null) {
            $this->addColumn(
                '{{%order_items}}',
                'retail_unit_price',
                $this->decimal(12, 2)->null()->after('unit_price')
            );
            $this->addColumn(
                '{{%order_items}}',
                'dealer_unit_price',
                $this->decimal(12, 2)->null()->after('retail_unit_price')
            );
            $this->update('{{%order_items}}', [
                'retail_unit_price' => new \yii\db\Expression('unit_price'),
            ]);
        }
    }

    public function safeDown(): bool
    {
        if ($this->db->getTableSchema('{{%order_items}}')?->getColumn('dealer_unit_price') !== null) {
            $this->dropColumn('{{%order_items}}', 'dealer_unit_price');
            $this->dropColumn('{{%order_items}}', 'retail_unit_price');
        }

        if ($this->db->getTableSchema('{{%dealer_price_lists}}', true) !== null) {
            $this->dropForeignKey('fk_dealer_price_lists_uploaded_by', '{{%dealer_price_lists}}');
            $this->dropForeignKey('fk_dealer_price_lists_media_file_id', '{{%dealer_price_lists}}');
            $this->dropForeignKey('fk_dealer_price_lists_dealer_user_id', '{{%dealer_price_lists}}');
            $this->dropTable('{{%dealer_price_lists}}');
        }

        if ($this->db->getTableSchema('{{%dealer_profiles}}')?->getColumn('assigned_admin_id') === null) {
            $this->addColumn('{{%dealer_profiles}}', 'assigned_admin_id', $this->integer()->null());
        }

        if ($this->db->getTableSchema('{{%dealer_profiles}}')?->getColumn('assigned_manager_id') !== null) {
            $this->dropForeignKey('fk_dealer_profiles_assigned_manager_id', '{{%dealer_profiles}}');
            $this->dropIndex('idx_dealer_profiles_assigned_manager_id', '{{%dealer_profiles}}');
            $this->dropColumn('{{%dealer_profiles}}', 'assigned_manager_id');
        }

        if ($this->db->getTableSchema('{{%dealer_profiles}}')?->getColumn('personal_discount_percent') !== null) {
            $this->dropColumn('{{%dealer_profiles}}', 'personal_discount_percent');
        }

        if ($this->db->getTableSchema('{{%dealer_managers}}', true) !== null) {
            $this->dropTable('{{%dealer_managers}}');
        }

        return true;
    }

    private function migrateAssignedAdminsToManagers(): void
    {
        if (!$this->db->getTableSchema('{{%dealer_profiles}}')?->getColumn('assigned_admin_id')) {
            return;
        }

        $rows = (new \yii\db\Query())
            ->from('{{%dealer_profiles}} dp')
            ->innerJoin('{{%admin_users}} au', 'au.id = dp.assigned_admin_id')
            ->select([
                'admin_id' => 'au.id',
                'name' => 'au.name',
                'phone' => 'au.phone',
                'email' => 'au.email',
                'work_hours' => 'au.work_hours',
                'public_role' => 'au.public_role',
            ])
            ->groupBy(['au.id'])
            ->all();

        $adminToManager = [];
        $now = date('Y-m-d H:i:s');
        foreach ($rows as $row) {
            $this->insert('{{%dealer_managers}}', [
                'name' => (string)$row['name'],
                'phone' => $row['phone'],
                'email' => $row['email'],
                'work_hours' => $row['work_hours'],
                'role_label' => $row['public_role'],
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $adminToManager[(int)$row['admin_id']] = (int)$this->db->getLastInsertID();
        }

        $profiles = (new \yii\db\Query())
            ->from('{{%dealer_profiles}}')
            ->where(['not', ['assigned_admin_id' => null]])
            ->select(['user_id', 'assigned_admin_id'])
            ->all();

        foreach ($profiles as $profile) {
            $managerId = $adminToManager[(int)$profile['assigned_admin_id']] ?? null;
            if ($managerId === null) {
                continue;
            }
            $this->update('{{%dealer_profiles}}', [
                'assigned_manager_id' => $managerId,
            ], ['user_id' => (int)$profile['user_id']]);
        }
    }
}
