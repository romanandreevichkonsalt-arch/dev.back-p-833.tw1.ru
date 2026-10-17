<?php

use app\services\content\BlockTypeRegistry;
use yii\db\Migration;
use yii\db\Query;

/**
 * Регионы и адрес офиса страницы «Контакты» — из data/content/pages/contacts.json (contactsLocations.js).
 */
class m261016_180000_sync_contacts_locations extends Migration
{
    public function safeUp(): void
    {
        $path = Yii::getAlias('@app/data/content/pages/contacts.json');
        if (!is_file($path)) {
            return;
        }

        $pageData = json_decode((string)file_get_contents($path), true);
        if (!is_array($pageData)) {
            return;
        }

        $pageId = (new Query())
            ->select('id')
            ->from('{{%content_pages}}')
            ->where(['slug' => 'contacts'])
            ->scalar($this->db);

        if ($pageId === false) {
            return;
        }

        $pageId = (int)$pageId;
        $now = date('Y-m-d H:i:s');

        if (isset($pageData['regions']) && is_array($pageData['regions'])) {
            $regions = $pageData['regions'];
            $this->upsertBlock(
                $pageId,
                'regions',
                BlockTypeRegistry::TYPE_REGIONS,
                $regions,
                3,
                $now
            );
        }

        if (isset($pageData['info']) && is_array($pageData['info'])) {
            $this->upsertBlock(
                $pageId,
                'info',
                BlockTypeRegistry::TYPE_INFO_CONTACTS,
                $pageData['info'],
                1,
                $now
            );
        }
    }

    public function safeDown(): void
    {
        // Откат содержимого блоков не выполняется.
    }

    /**
     * @param array<string, mixed>|array<int, mixed> $data
     */
    private function upsertBlock(
        int $pageId,
        string $blockKey,
        string $blockType,
        array $data,
        int $sortOrder,
        string $now
    ): void {
        $encoded = json_encode($data, JSON_UNESCAPED_UNICODE);
        $row = (new Query())
            ->select(['id'])
            ->from('{{%content_blocks}}')
            ->where(['page_id' => $pageId, 'block_key' => $blockKey])
            ->one($this->db);

        if ($row !== false) {
            $this->update('{{%content_blocks}}', [
                'block_type' => $blockType,
                'data' => $encoded,
                'updated_at' => $now,
            ], ['id' => (int)$row['id']]);

            return;
        }

        $this->insert('{{%content_blocks}}', [
            'page_id' => $pageId,
            'block_key' => $blockKey,
            'block_type' => $blockType,
            'data' => $encoded,
            'sort_order' => $sortOrder,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
