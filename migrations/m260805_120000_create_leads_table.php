<?php

use yii\db\Migration;

class m260805_120000_create_leads_table extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%leads}}', [
            'id' => $this->primaryKey(),
            'type' => $this->string(32)->notNull(),
            'name' => $this->string(255)->notNull(),
            'email' => $this->string(255)->null(),
            'phone' => $this->string(20)->notNull(),
            'comment' => $this->text()->null(),
            'consent' => $this->boolean()->notNull(),
            'studio' => $this->string(255)->null(),
            'portfolio' => $this->string(1024)->null(),
            'city' => $this->string(255)->null(),
            'created_at' => $this->dateTime()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
        ]);

        $this->createIndex('idx_leads_type', '{{%leads}}', 'type');
        $this->createIndex('idx_leads_created_at', '{{%leads}}', 'created_at');
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%leads}}');
    }
}
