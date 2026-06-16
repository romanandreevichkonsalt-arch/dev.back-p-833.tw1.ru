<?php

use yii\db\Migration;

class m260616_100300_create_attribute_definitions_table extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%attribute_definitions}}', [
            'id' => $this->primaryKey(),
            'code' => $this->string(64)->notNull()->unique(),
            'name' => $this->string(255)->notNull(),
            'type' => $this->string(16)->notNull(),
            'is_filterable' => $this->boolean()->notNull()->defaultValue(true),
            'is_required' => $this->boolean()->notNull()->defaultValue(false),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);

        $this->createIndex('idx_attribute_definitions_type', '{{%attribute_definitions}}', 'type');
        $this->createIndex('idx_attribute_definitions_is_filterable', '{{%attribute_definitions}}', 'is_filterable');
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%attribute_definitions}}');
    }
}
