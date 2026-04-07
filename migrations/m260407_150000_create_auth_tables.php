<?php

use yii\db\Migration;

class m260407_150000_create_auth_tables extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%users}}', [
            'id' => $this->primaryKey(),
            'phone' => $this->string(11)->notNull()->unique(),
            'username' => $this->string(255)->null(),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);

        $this->createTable('{{%sms_codes}}', [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer()->notNull(),
            'phone' => $this->string(11)->notNull(),
            'code' => $this->string(6)->notNull(),
            'expires_at' => $this->dateTime()->notNull(),
            'used_at' => $this->dateTime()->null(),
            'created_at' => $this->dateTime()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
        ]);
        $this->createIndex('idx_sms_codes_phone', '{{%sms_codes}}', 'phone');
        $this->addForeignKey(
            'fk_sms_codes_user_id',
            '{{%sms_codes}}',
            'user_id',
            '{{%users}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->createTable('{{%api_access_tokens}}', [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer()->notNull(),
            'token_hash' => $this->string(64)->notNull()->unique(),
            'expires_at' => $this->dateTime()->notNull(),
            'revoked_at' => $this->dateTime()->null(),
            'created_at' => $this->dateTime()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
        ]);
        $this->createIndex('idx_api_access_tokens_user_id', '{{%api_access_tokens}}', 'user_id');
        $this->addForeignKey(
            'fk_api_access_tokens_user_id',
            '{{%api_access_tokens}}',
            'user_id',
            '{{%users}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
    }

    public function safeDown(): void
    {
        $this->dropForeignKey('fk_api_access_tokens_user_id', '{{%api_access_tokens}}');
        $this->dropTable('{{%api_access_tokens}}');

        $this->dropForeignKey('fk_sms_codes_user_id', '{{%sms_codes}}');
        $this->dropTable('{{%sms_codes}}');

        $this->dropTable('{{%users}}');
    }
}
