<?php

use yii\db\Migration;

class m260822_120000_dealer_promo_cashback extends Migration
{
    public function safeUp(): void
    {
        if (!$this->tableExists('{{%promo_code_templates}}')) {
            $this->createTable('{{%promo_code_templates}}', [
                'id' => $this->primaryKey(),
                'code' => $this->string(64)->notNull()->unique(),
                'title' => $this->string(255)->notNull(),
                'discount_percent' => $this->decimal(5, 2)->notNull()->defaultValue(10),
                'type' => $this->string(32)->notNull()->defaultValue('custom'),
                'is_single_use' => $this->boolean()->notNull()->defaultValue(true),
                'is_active' => $this->boolean()->notNull()->defaultValue(true),
                'default_valid_days' => $this->integer()->null(),
                'created_at' => $this->dateTime()->notNull(),
                'updated_at' => $this->dateTime()->notNull(),
            ]);
        }

        $now = date('Y-m-d H:i:s');
        $exists = (new \yii\db\Query())
            ->from('{{%promo_code_templates}}')
            ->where(['code' => 'VYSTAVKA'])
            ->exists($this->db);
        if (!$exists) {
            $this->insert('{{%promo_code_templates}}', [
                'code' => 'VYSTAVKA',
                'title' => 'Экспозиционный образец',
                'discount_percent' => 10,
                'type' => 'exhibition',
                'is_single_use' => true,
                'is_active' => true,
                'default_valid_days' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (!$this->tableExists('{{%dealer_promo_grants}}')) {
            $this->createTable('{{%dealer_promo_grants}}', [
                'id' => $this->primaryKey(),
                'user_id' => $this->integer()->notNull(),
                'template_id' => $this->integer()->null(),
                'code' => $this->string(64)->notNull(),
                'discount_percent' => $this->decimal(5, 2)->notNull(),
                'catalog_model_id' => $this->integer()->null(),
                'expires_at' => $this->dateTime()->null(),
                'used_at' => $this->dateTime()->null(),
                'used_order_id' => $this->integer()->null(),
                'is_active' => $this->boolean()->notNull()->defaultValue(true),
                'source' => $this->string(32)->notNull()->defaultValue('admin'),
                'granted_by_admin_id' => $this->integer()->null(),
                'created_at' => $this->dateTime()->notNull(),
            ]);
            $this->createIndex('idx_dealer_promo_grants_user_id', '{{%dealer_promo_grants}}', 'user_id');
            $this->createIndex('idx_dealer_promo_grants_code', '{{%dealer_promo_grants}}', 'code');
        }
        $this->addForeignKeyIfMissing('fk_dealer_promo_grants_user_id', '{{%dealer_promo_grants}}', 'user_id', '{{%users}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKeyIfMissing('fk_dealer_promo_grants_template_id', '{{%dealer_promo_grants}}', 'template_id', '{{%promo_code_templates}}', 'id', 'SET NULL', 'CASCADE');
        $this->addForeignKeyIfMissing('fk_dealer_promo_grants_model_id', '{{%dealer_promo_grants}}', 'catalog_model_id', '{{%catalog_models}}', 'id', 'SET NULL', 'CASCADE');
        $this->addForeignKeyIfMissing('fk_dealer_promo_grants_order_id', '{{%dealer_promo_grants}}', 'used_order_id', '{{%orders}}', 'id', 'SET NULL', 'CASCADE');
        $this->addForeignKeyIfMissing('fk_dealer_promo_grants_admin_id', '{{%dealer_promo_grants}}', 'granted_by_admin_id', '{{%admin_users}}', 'id', 'SET NULL', 'CASCADE');

        if (!$this->tableExists('{{%dealer_cashback_accounts}}')) {
            $this->createTable('{{%dealer_cashback_accounts}}', [
                'user_id' => $this->integer()->notNull(),
                'balance' => $this->decimal(12, 2)->notNull()->defaultValue(0),
                'period_total' => $this->decimal(14, 2)->notNull()->defaultValue(0),
                'period_year_month' => $this->string(7)->notNull(),
                'updated_at' => $this->dateTime()->notNull(),
            ]);
            $this->addPrimaryKey('pk_dealer_cashback_accounts', '{{%dealer_cashback_accounts}}', 'user_id');
        }
        $this->addForeignKeyIfMissing('fk_dealer_cashback_accounts_user_id', '{{%dealer_cashback_accounts}}', 'user_id', '{{%users}}', 'id', 'CASCADE', 'CASCADE');

        if (!$this->tableExists('{{%dealer_cashback_ledger}}')) {
            $this->createTable('{{%dealer_cashback_ledger}}', [
                'id' => $this->primaryKey(),
                'user_id' => $this->integer()->notNull(),
                'type' => $this->string(32)->notNull(),
                'amount' => $this->decimal(12, 2)->notNull(),
                'balance_after' => $this->decimal(12, 2)->notNull(),
                'period_year_month' => $this->string(7)->null(),
                'expires_at' => $this->dateTime()->null(),
                'order_id' => $this->integer()->null(),
                'comment' => $this->text()->null(),
                'created_at' => $this->dateTime()->notNull(),
            ]);
            $this->createIndex('idx_dealer_cashback_ledger_user_id', '{{%dealer_cashback_ledger}}', 'user_id');
        }
        $this->addForeignKeyIfMissing('fk_dealer_cashback_ledger_user_id', '{{%dealer_cashback_ledger}}', 'user_id', '{{%users}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKeyIfMissing('fk_dealer_cashback_ledger_order_id', '{{%dealer_cashback_ledger}}', 'order_id', '{{%orders}}', 'id', 'SET NULL', 'CASCADE');

        if (!$this->tableExists('{{%dealer_cart_checkout}}')) {
            $this->createTable('{{%dealer_cart_checkout}}', [
                'user_id' => $this->integer()->notNull(),
                'promo_grant_id' => $this->integer()->null(),
                'cashback_amount' => $this->decimal(12, 2)->notNull()->defaultValue(0),
                'updated_at' => $this->dateTime()->notNull(),
            ]);
            $this->addPrimaryKey('pk_dealer_cart_checkout', '{{%dealer_cart_checkout}}', 'user_id');
        }
        $this->addForeignKeyIfMissing('fk_dealer_cart_checkout_user_id', '{{%dealer_cart_checkout}}', 'user_id', '{{%users}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKeyIfMissing('fk_dealer_cart_checkout_promo_id', '{{%dealer_cart_checkout}}', 'promo_grant_id', '{{%dealer_promo_grants}}', 'id', 'SET NULL', 'CASCADE');

        if (!$this->columnExists('{{%orders}}', 'subtotal_amount')) {
            $this->addColumn('{{%orders}}', 'subtotal_amount', $this->decimal(12, 2)->notNull()->defaultValue(0)->after('total_amount'));
        }
        if (!$this->columnExists('{{%orders}}', 'promo_grant_id')) {
            $this->addColumn('{{%orders}}', 'promo_grant_id', $this->integer()->null()->after('subtotal_amount'));
        }
        if (!$this->columnExists('{{%orders}}', 'promo_discount_amount')) {
            $this->addColumn('{{%orders}}', 'promo_discount_amount', $this->decimal(12, 2)->notNull()->defaultValue(0)->after('promo_grant_id'));
        }
        if (!$this->columnExists('{{%orders}}', 'cashback_used_amount')) {
            $this->addColumn('{{%orders}}', 'cashback_used_amount', $this->decimal(12, 2)->notNull()->defaultValue(0)->after('promo_discount_amount'));
        }
        $this->addForeignKeyIfMissing('fk_orders_promo_grant_id', '{{%orders}}', 'promo_grant_id', '{{%dealer_promo_grants}}', 'id', 'SET NULL', 'CASCADE');
    }

    public function safeDown(): void
    {
        $this->dropForeignKey('fk_orders_promo_grant_id', '{{%orders}}');
        $this->dropColumn('{{%orders}}', 'cashback_used_amount');
        $this->dropColumn('{{%orders}}', 'promo_discount_amount');
        $this->dropColumn('{{%orders}}', 'promo_grant_id');
        $this->dropColumn('{{%orders}}', 'subtotal_amount');

        $this->dropForeignKey('fk_dealer_cart_checkout_promo_id', '{{%dealer_cart_checkout}}');
        $this->dropForeignKey('fk_dealer_cart_checkout_user_id', '{{%dealer_cart_checkout}}');
        $this->dropTable('{{%dealer_cart_checkout}}');

        $this->dropForeignKey('fk_dealer_cashback_ledger_order_id', '{{%dealer_cashback_ledger}}');
        $this->dropForeignKey('fk_dealer_cashback_ledger_user_id', '{{%dealer_cashback_ledger}}');
        $this->dropTable('{{%dealer_cashback_ledger}}');

        $this->dropForeignKey('fk_dealer_cashback_accounts_user_id', '{{%dealer_cashback_accounts}}');
        $this->dropTable('{{%dealer_cashback_accounts}}');

        $this->dropForeignKey('fk_dealer_promo_grants_admin_id', '{{%dealer_promo_grants}}');
        $this->dropForeignKey('fk_dealer_promo_grants_order_id', '{{%dealer_promo_grants}}');
        $this->dropForeignKey('fk_dealer_promo_grants_model_id', '{{%dealer_promo_grants}}');
        $this->dropForeignKey('fk_dealer_promo_grants_template_id', '{{%dealer_promo_grants}}');
        $this->dropForeignKey('fk_dealer_promo_grants_user_id', '{{%dealer_promo_grants}}');
        $this->dropTable('{{%dealer_promo_grants}}');

        $this->dropTable('{{%promo_code_templates}}');
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
