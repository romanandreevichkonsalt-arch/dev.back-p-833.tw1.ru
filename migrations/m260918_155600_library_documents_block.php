<?php

use app\services\content\BlockTypeRegistry;
use yii\db\Migration;
use yii\db\Query;

class m260918_155600_library_documents_block extends Migration
{
    /**
     * @return array<string, mixed>
     */
    private function defaultDocumentsData(): array
    {
        return [
            'items' => [
                [
                    'title' => 'Технический каталог',
                    'subtitle' => 'Спецификации изделий, размеры, материалы и варианты обивки.',
                    'fileUrl' => '',
                ],
                [
                    'title' => 'Руководство по эксплуатации',
                    'subtitle' => 'Рекомендации по уходу за мебелью и сохранению внешнего вида.',
                    'fileUrl' => '',
                ],
                [
                    'title' => 'Сертификаты пожарной безопасности',
                    'subtitle' => 'Документы о соответствии материалов требованиям безопасности.',
                    'fileUrl' => '',
                ],
            ],
        ];
    }

    public function safeUp(): void
    {
        $pageId = (new Query())
            ->select('id')
            ->from('{{%content_pages}}')
            ->where(['slug' => 'library'])
            ->scalar();

        if ($pageId === false) {
            return;
        }

        $pageId = (int)$pageId;
        $now = date('Y-m-d H:i:s');

        $exists = (new Query())
            ->from('{{%content_blocks}}')
            ->where(['page_id' => $pageId, 'block_key' => 'documents'])
            ->exists();

        if (!$exists) {
            $this->insert('{{%content_blocks}}', [
                'page_id' => $pageId,
                'block_key' => 'documents',
                'block_type' => BlockTypeRegistry::TYPE_LIBRARY_DOCUMENTS,
                'data' => json_encode($this->defaultDocumentsData(), JSON_UNESCAPED_UNICODE),
                'sort_order' => 4,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function safeDown(): void
    {
        $pageId = (new Query())
            ->select('id')
            ->from('{{%content_pages}}')
            ->where(['slug' => 'library'])
            ->scalar();

        if ($pageId === false) {
            return;
        }

        $this->delete('{{%content_blocks}}', [
            'page_id' => (int)$pageId,
            'block_key' => 'documents',
        ]);
    }
}
