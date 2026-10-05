<?php

use yii\db\Migration;

class m260930_120000_user_agreement_page extends Migration
{
    public function safeUp(): void
    {
        $slug = 'user-agreement';
        if ($this->db->createCommand(
            'SELECT id FROM {{%content_pages}} WHERE slug = :slug',
            [':slug' => $slug]
        )->queryScalar() !== false) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $this->insert('{{%content_pages}}', [
            'slug' => $slug,
            'title' => \app\models\ContentPage::titleForSlug($slug),
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $pageId = (int)$this->db->getLastInsertID();

        $this->insert('{{%content_blocks}}', [
            'page_id' => $pageId,
            'block_key' => 'blocks',
            'block_type' => \app\services\content\BlockTypeRegistry::TYPE_ARTICLE_BLOCKS,
            'data' => '[]',
            'sort_order' => 0,
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function safeDown(): void
    {
        $pageId = $this->db->createCommand(
            'SELECT id FROM {{%content_pages}} WHERE slug = :slug',
            [':slug' => 'user-agreement']
        )->queryScalar();

        if ($pageId === false) {
            return;
        }

        $this->delete('{{%content_blocks}}', ['page_id' => (int)$pageId]);
        $this->delete('{{%content_pages}}', ['id' => (int)$pageId]);
    }
}
