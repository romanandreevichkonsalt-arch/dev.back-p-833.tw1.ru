<?php

use yii\db\Migration;

class m260811_150000_media_documents_folder extends Migration
{
    public function safeUp(): void
    {
        $exists = (new \yii\db\Query())
            ->from('{{%media_folders}}')
            ->where(['slug' => 'documents'])
            ->exists($this->db);

        if (!$exists) {
            $this->insert('{{%media_folders}}', [
                'slug' => 'documents',
                'label' => 'Документы',
                'sort_order' => 45,
            ]);
        }
    }

    public function safeDown(): void
    {
        $this->delete('{{%media_folders}}', ['slug' => 'documents']);
    }
}
