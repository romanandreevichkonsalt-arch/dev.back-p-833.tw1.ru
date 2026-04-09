<?php

use yii\db\Migration;

class m260407_190000_create_user_profiles_table extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%user_profiles}}', [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer()->notNull()->unique(),
            'first_name' => $this->string(255)->null(),
            'last_name' => $this->string(255)->null(),
            'display_name' => $this->string(255)->null(),
            'email' => $this->string(255)->null(),
            'avatar_url' => $this->string(1024)->null(),
            'yandex_login' => $this->string(255)->null(),
            'raw_payload' => $this->text()->null(),
            'created_at' => $this->dateTime()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
            'updated_at' => $this->dateTime()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
        ]);

        $this->addForeignKey(
            'fk_user_profiles_user_id',
            '{{%user_profiles}}',
            'user_id',
            '{{%users}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
    }

    public function safeDown(): void
    {
        $this->dropForeignKey('fk_user_profiles_user_id', '{{%user_profiles}}');
        $this->dropTable('{{%user_profiles}}');
    }
}
