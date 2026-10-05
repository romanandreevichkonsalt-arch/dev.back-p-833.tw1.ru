<?php

use yii\db\Migration;

class m260805_140000_create_admin_users_table extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%admin_users}}', [
            'id' => $this->primaryKey(),
            'username' => $this->string(64)->notNull()->unique(),
            'password_hash' => $this->string(255)->notNull(),
            'auth_key' => $this->string(32)->notNull(),
            'email' => $this->string(255)->null(),
            'name' => $this->string(255)->notNull(),
            'role' => $this->string(32)->notNull()->defaultValue('admin'),
            'is_active' => $this->boolean()->notNull()->defaultValue(true),
            'last_login_at' => $this->dateTime()->null(),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);

        $this->createIndex('idx_admin_users_role', '{{%admin_users}}', 'role');
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%admin_users}}');
    }
}
