<?php

use yii\db\Migration;

class m260616_200100_add_admin_auth_and_soft_delete extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn('{{%users}}', 'password_hash', $this->string(255)->null()->after('username'));
        $this->addColumn('{{%users}}', 'auth_key', $this->string(32)->null()->after('password_hash'));

        $this->addColumn('{{%categories}}', 'deleted_at', $this->dateTime()->null()->after('updated_at'));
        $this->addColumn('{{%products}}', 'deleted_at', $this->dateTime()->null()->after('updated_at'));

        $this->createIndex('idx_categories_deleted_at', '{{%categories}}', 'deleted_at');
        $this->createIndex('idx_products_deleted_at', '{{%products}}', 'deleted_at');
    }

    public function safeDown(): void
    {
        $this->dropIndex('idx_products_deleted_at', '{{%products}}');
        $this->dropIndex('idx_categories_deleted_at', '{{%categories}}');
        $this->dropColumn('{{%products}}', 'deleted_at');
        $this->dropColumn('{{%categories}}', 'deleted_at');
        $this->dropColumn('{{%users}}', 'auth_key');
        $this->dropColumn('{{%users}}', 'password_hash');
    }
}
