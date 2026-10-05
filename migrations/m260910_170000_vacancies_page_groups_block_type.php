<?php

use yii\db\Migration;

class m260910_170000_vacancies_page_groups_block_type extends Migration
{
    public function safeUp(): void
    {
        $pageId = $this->db->createCommand(
            'SELECT id FROM {{%content_pages}} WHERE slug = :slug',
            [':slug' => 'vacancies']
        )->queryScalar();

        if ($pageId === false) {
            return;
        }

        $this->update(
            '{{%content_blocks}}',
            ['block_type' => 'vacancies_groups'],
            ['page_id' => (int)$pageId, 'block_key' => 'groups']
        );
    }

    public function safeDown(): void
    {
        $pageId = $this->db->createCommand(
            'SELECT id FROM {{%content_pages}} WHERE slug = :slug',
            [':slug' => 'vacancies']
        )->queryScalar();

        if ($pageId === false) {
            return;
        }

        $this->update(
            '{{%content_blocks}}',
            ['block_type' => 'json'],
            ['page_id' => (int)$pageId, 'block_key' => 'groups']
        );
    }
}
