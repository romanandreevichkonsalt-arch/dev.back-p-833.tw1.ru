<?php

use yii\db\Migration;

class m260820_200000_dealer_auth extends Migration
{
    public function safeUp(): void
    {
        if (!$this->columnExists('{{%users}}', 'type')) {
            $this->addColumn('{{%users}}', 'type', $this->string(16)->notNull()->defaultValue('customer')->after('username'));
        }
        if (!$this->columnExists('{{%users}}', 'password_hash')) {
            $this->addColumn('{{%users}}', 'password_hash', $this->string(255)->null()->after('type'));
        }
        if (!$this->columnExists('{{%users}}', 'is_blocked')) {
            $this->addColumn('{{%users}}', 'is_blocked', $this->boolean()->notNull()->defaultValue(false)->after('password_hash'));
        }
        if (!$this->columnExists('{{%users}}', 'profile_completed_at')) {
            $this->addColumn('{{%users}}', 'profile_completed_at', $this->dateTime()->null()->after('is_blocked'));
        }

        $this->alterColumn('{{%users}}', 'phone', $this->string(11)->null());

        if (!$this->indexExists('{{%users}}', 'idx_users_type')) {
            $this->createIndex('idx_users_type', '{{%users}}', 'type');
        }
        if (!$this->indexExists('{{%users}}', 'uniq_users_username')) {
            $this->createIndex('uniq_users_username', '{{%users}}', 'username', true);
        }

        if (!$this->tableExists('{{%dealer_profiles}}')) {
            $this->createTable('{{%dealer_profiles}}', [
                'user_id' => $this->integer()->notNull(),
                'inn' => $this->string(12)->notNull(),
                'company_name' => $this->string(255)->notNull(),
                'manager_name' => $this->string(255)->null(),
                'email' => $this->string(255)->null(),
                'dealer_type' => $this->string(16)->notNull()->defaultValue('new'),
                'credentials_sent_at' => $this->dateTime()->null(),
                'first_login_at' => $this->dateTime()->null(),
                'created_by_admin_id' => $this->integer()->null(),
                'created_at' => $this->dateTime()->notNull(),
                'updated_at' => $this->dateTime()->notNull(),
            ]);
            $this->addPrimaryKey('pk_dealer_profiles', '{{%dealer_profiles}}', 'user_id');
            $this->createIndex('uniq_dealer_profiles_inn', '{{%dealer_profiles}}', 'inn', true);
        }
        $this->addForeignKeyIfMissing(
            'fk_dealer_profiles_user_id',
            '{{%dealer_profiles}}',
            'user_id',
            '{{%users}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
        $this->addForeignKeyIfMissing(
            'fk_dealer_profiles_created_by',
            '{{%dealer_profiles}}',
            'created_by_admin_id',
            '{{%admin_users}}',
            'id',
            'SET NULL',
            'CASCADE'
        );

        if (!$this->tableExists('{{%dealer_activity_logs}}')) {
            $this->createTable('{{%dealer_activity_logs}}', [
                'id' => $this->primaryKey(),
                'user_id' => $this->integer()->notNull(),
                'action' => $this->string(64)->notNull(),
                'context' => $this->text()->null(),
                'ip' => $this->string(45)->null(),
                'user_agent' => $this->string(512)->null(),
                'created_at' => $this->dateTime()->notNull(),
            ]);
            $this->createIndex('idx_dealer_activity_logs_user_id', '{{%dealer_activity_logs}}', 'user_id');
            $this->createIndex('idx_dealer_activity_logs_action', '{{%dealer_activity_logs}}', 'action');
        }
        $this->addForeignKeyIfMissing(
            'fk_dealer_activity_logs_user_id',
            '{{%dealer_activity_logs}}',
            'user_id',
            '{{%users}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        if (!$this->tableExists('{{%dealer_credentials_log}}')) {
            $this->createTable('{{%dealer_credentials_log}}', [
                'id' => $this->primaryKey(),
                'user_id' => $this->integer()->notNull(),
                'admin_user_id' => $this->integer()->null(),
                'email' => $this->string(255)->notNull(),
                'is_success' => $this->boolean()->notNull()->defaultValue(false),
                'error_message' => $this->text()->null(),
                'created_at' => $this->dateTime()->notNull(),
            ]);
            $this->createIndex('idx_dealer_credentials_log_user_id', '{{%dealer_credentials_log}}', 'user_id');
        }
        $this->addForeignKeyIfMissing(
            'fk_dealer_credentials_log_user_id',
            '{{%dealer_credentials_log}}',
            'user_id',
            '{{%users}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
        $this->addForeignKeyIfMissing(
            'fk_dealer_credentials_log_admin_user_id',
            '{{%dealer_credentials_log}}',
            'admin_user_id',
            '{{%admin_users}}',
            'id',
            'SET NULL',
            'CASCADE'
        );

        if (!$this->columnExists('{{%orders}}', 'customer_user_id')) {
            $this->addColumn('{{%orders}}', 'customer_user_id', $this->integer()->null()->after('user_id'));
        }
        $this->addForeignKeyIfMissing(
            'fk_orders_customer_user_id',
            '{{%orders}}',
            'customer_user_id',
            '{{%users}}',
            'id',
            'SET NULL',
            'CASCADE'
        );
    }

    public function safeDown(): void
    {
        $this->dropForeignKey('fk_orders_customer_user_id', '{{%orders}}');
        $this->dropColumn('{{%orders}}', 'customer_user_id');

        $this->dropForeignKey('fk_dealer_credentials_log_admin_user_id', '{{%dealer_credentials_log}}');
        $this->dropForeignKey('fk_dealer_credentials_log_user_id', '{{%dealer_credentials_log}}');
        $this->dropTable('{{%dealer_credentials_log}}');

        $this->dropForeignKey('fk_dealer_activity_logs_user_id', '{{%dealer_activity_logs}}');
        $this->dropTable('{{%dealer_activity_logs}}');

        $this->dropForeignKey('fk_dealer_profiles_created_by', '{{%dealer_profiles}}');
        $this->dropForeignKey('fk_dealer_profiles_user_id', '{{%dealer_profiles}}');
        $this->dropTable('{{%dealer_profiles}}');

        $this->dropIndex('uniq_users_username', '{{%users}}');
        $this->dropIndex('idx_users_type', '{{%users}}');

        $this->dropColumn('{{%users}}', 'profile_completed_at');
        $this->dropColumn('{{%users}}', 'is_blocked');
        $this->dropColumn('{{%users}}', 'password_hash');
        $this->dropColumn('{{%users}}', 'type');

        $this->alterColumn('{{%users}}', 'phone', $this->string(11)->notNull());
    }

    private function columnExists(string $table, string $column): bool
    {
        $schema = $this->db->schema->getTableSchema($table, true);

        return $schema !== null && isset($schema->columns[$column]);
    }

    private function tableExists(string $table): bool
    {
        return $this->db->schema->getTableSchema($table, true) !== null;
    }

    private function indexExists(string $table, string $name): bool
    {
        $rawTable = $this->db->schema->getRawTableName($table);
        $rows = $this->db->createCommand(
            'SHOW INDEX FROM `' . str_replace('`', '``', $rawTable) . '` WHERE Key_name = :name',
            [':name' => $name]
        )->queryAll();

        return $rows !== [];
    }

    private function foreignKeyExists(string $name): bool
    {
        $row = $this->db->createCommand(
            'SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = :name AND CONSTRAINT_TYPE = \'FOREIGN KEY\'',
            [':name' => $name]
        )->queryScalar();

        return $row !== false && $row !== null;
    }

    private function addForeignKeyIfMissing(
        string $name,
        string $table,
        string $columns,
        string $refTable,
        string $refColumns,
        ?string $delete = null,
        ?string $update = null,
    ): void {
        if ($this->foreignKeyExists($name)) {
            return;
        }

        $this->addForeignKey($name, $table, $columns, $refTable, $refColumns, $delete, $update);
    }
}
