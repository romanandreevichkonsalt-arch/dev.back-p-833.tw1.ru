<?php

use yii\db\Migration;

class m260407_170000_create_external_identities_table extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%external_identities}}', [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer()->notNull(),
            'provider' => $this->string(32)->notNull(),
            'provider_user_id' => $this->string(255)->notNull(),
            'email' => $this->string(255)->null(),
            'raw_payload' => $this->text()->null(),
            'created_at' => $this->dateTime()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
            'updated_at' => $this->dateTime()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
        ]);

        $this->createIndex(
            'ux_external_identities_provider_user',
            '{{%external_identities}}',
            ['provider', 'provider_user_id'],
            true
        );
        $this->createIndex('idx_external_identities_user_id', '{{%external_identities}}', 'user_id');

        $this->addForeignKey(
            'fk_external_identities_user_id',
            '{{%external_identities}}',
            'user_id',
            '{{%users}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
    }

    public function safeDown(): void
    {
        $this->dropForeignKey('fk_external_identities_user_id', '{{%external_identities}}');
        $this->dropTable('{{%external_identities}}');
    }
}
