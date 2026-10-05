<?php

use yii\db\Migration;

class m260909_200000_seed_about_vacancies_pages extends Migration
{
    public function safeUp(): void
    {
        foreach (['about', 'vacancies'] as $slug) {
            if ($this->db->createCommand(
                'SELECT id FROM {{%content_pages}} WHERE slug = :slug',
                [':slug' => $slug]
            )->queryScalar() !== false) {
                continue;
            }

            $path = Yii::getAlias("@app/data/content/pages/{$slug}.json");
            if (!is_file($path)) {
                continue;
            }

            $data = json_decode((string)file_get_contents($path), true);
            if (!is_array($data)) {
                continue;
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
            $sortOrder = 0;

            foreach ($data as $blockKey => $blockValue) {
                $this->insert('{{%content_blocks}}', [
                    'page_id' => $pageId,
                    'block_key' => (string)$blockKey,
                    'block_type' => \app\services\content\BlockTypeRegistry::detect($slug, (string)$blockKey, $blockValue),
                    'data' => json_encode($blockValue, JSON_UNESCAPED_UNICODE),
                    'sort_order' => $sortOrder++,
                    'is_active' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function safeDown(): void
    {
        foreach (['about', 'vacancies'] as $slug) {
            $pageId = $this->db->createCommand(
                'SELECT id FROM {{%content_pages}} WHERE slug = :slug',
                [':slug' => $slug]
            )->queryScalar();

            if ($pageId === false) {
                continue;
            }

            $this->delete('{{%content_blocks}}', ['page_id' => (int)$pageId]);
            $this->delete('{{%content_pages}}', ['id' => (int)$pageId]);
        }
    }
}
