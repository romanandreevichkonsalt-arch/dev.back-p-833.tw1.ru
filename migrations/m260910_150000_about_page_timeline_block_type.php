<?php

use yii\db\Migration;

class m260910_150000_about_page_timeline_block_type extends Migration
{
    public function safeUp(): void
    {
        $pageId = $this->db->createCommand(
            'SELECT id FROM {{%content_pages}} WHERE slug = :slug',
            [':slug' => 'about']
        )->queryScalar();

        if ($pageId === false) {
            return;
        }

        $this->update(
            '{{%content_blocks}}',
            ['block_type' => 'about_timeline'],
            ['page_id' => (int)$pageId, 'block_key' => 'timeline']
        );
    }

    public function safeDown(): void
    {
        $pageId = $this->db->createCommand(
            'SELECT id FROM {{%content_pages}} WHERE slug = :slug',
            [':slug' => 'about']
        )->queryScalar();

        if ($pageId === false) {
            return;
        }

        $this->update(
            '{{%content_blocks}}',
            ['block_type' => 'json'],
            ['page_id' => (int)$pageId, 'block_key' => 'timeline']
        );
    }
}
