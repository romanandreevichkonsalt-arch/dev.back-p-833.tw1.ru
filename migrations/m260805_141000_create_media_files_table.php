<?php

use yii\db\Migration;

class m260805_141000_create_media_files_table extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%media_files}}', [
            'id' => $this->primaryKey(),
            'filename' => $this->string(255)->notNull(),
            'path' => $this->string(512)->notNull()->unique(),
            'mime' => $this->string(128)->notNull(),
            'size' => $this->integer()->notNull(),
            'width' => $this->integer()->null(),
            'height' => $this->integer()->null(),
            'alt' => $this->string(512)->null(),
            'created_at' => $this->dateTime()->notNull(),
        ]);

        $this->createIndex('idx_media_files_created_at', '{{%media_files}}', 'created_at');
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%media_files}}');
    }
}
