<?php

use app\services\catalog\CatalogListingSeoService;
use app\services\content\BlockTypeRegistry;
use yii\db\Migration;
use yii\db\Query;

class m261020_150000_catalog_seo_templates_block extends Migration
{
    public function safeUp(): void
    {
        $pageId = (new Query())
            ->select('id')
            ->from('{{%content_pages}}')
            ->where(['slug' => 'catalog'])
            ->scalar();

        if ($pageId === false) {
            return;
        }

        $pageId = (int)$pageId;
        $exists = (new Query())
            ->from('{{%content_blocks}}')
            ->where(['page_id' => $pageId, 'block_key' => CatalogListingSeoService::BLOCK_KEY])
            ->exists();

        if ($exists) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $this->insert('{{%content_blocks}}', [
            'page_id' => $pageId,
            'block_key' => CatalogListingSeoService::BLOCK_KEY,
            'block_type' => BlockTypeRegistry::TYPE_CATALOG_SEO_TEMPLATES,
            'data' => json_encode(CatalogListingSeoService::defaultTemplates(), JSON_UNESCAPED_UNICODE),
            'sort_order' => 0,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function safeDown(): void
    {
        $pageId = (new Query())
            ->select('id')
            ->from('{{%content_pages}}')
            ->where(['slug' => 'catalog'])
            ->scalar();

        if ($pageId === false) {
            return;
        }

        $this->delete('{{%content_blocks}}', [
            'page_id' => (int)$pageId,
            'block_key' => CatalogListingSeoService::BLOCK_KEY,
        ]);
    }
}
