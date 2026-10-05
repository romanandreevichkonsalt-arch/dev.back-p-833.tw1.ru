<?php

use yii\db\Migration;

class m260910_160000_vacancies_page_admin_block_types extends Migration
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
            ['block_type' => 'vacancies_values'],
            ['page_id' => (int)$pageId, 'block_key' => 'values']
        );

        $this->update(
            '{{%content_blocks}}',
            ['block_type' => 'vacancies_gallery'],
            ['page_id' => (int)$pageId, 'block_key' => 'gallery']
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
            ['page_id' => (int)$pageId, 'block_key' => 'values']
        );

        $this->update(
            '{{%content_blocks}}',
            ['block_type' => 'gallery_simple'],
            ['page_id' => (int)$pageId, 'block_key' => 'gallery']
        );
    }
}
