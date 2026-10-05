<?php

use yii\db\Migration;

class m260910_140000_about_page_admin_block_types extends Migration
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
            ['block_type' => 'about_intro'],
            ['page_id' => (int)$pageId, 'block_key' => 'intro']
        );

        $this->update(
            '{{%content_blocks}}',
            ['block_type' => 'about_gallery'],
            ['page_id' => (int)$pageId, 'block_key' => 'community']
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
            ['page_id' => (int)$pageId, 'block_key' => 'intro']
        );
        $this->update(
            '{{%content_blocks}}',
            ['block_type' => 'json'],
            ['page_id' => (int)$pageId, 'block_key' => 'community']
        );
    }
}
