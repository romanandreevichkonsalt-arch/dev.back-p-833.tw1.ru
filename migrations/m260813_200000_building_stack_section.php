<?php

use app\services\content\BlockTypeRegistry;
use yii\db\Migration;
use yii\db\Query;

class m260813_200000_building_stack_section extends Migration
{
    public function safeUp(): void
    {
        $pageId = (new Query())
            ->select('id')
            ->from('{{%content_pages}}')
            ->where(['slug' => 'building'])
            ->scalar();

        if ($pageId === false) {
            return;
        }

        $pageId = (int)$pageId;
        $now = date('Y-m-d H:i:s');

        $stackRow = (new Query())
            ->select(['id', 'data', 'block_type'])
            ->from('{{%content_blocks}}')
            ->where(['page_id' => $pageId, 'block_key' => 'stack'])
            ->one();

        $standardsRow = (new Query())
            ->select(['data'])
            ->from('{{%content_blocks}}')
            ->where(['page_id' => $pageId, 'block_key' => 'standards'])
            ->one();

        if ($stackRow !== false) {
            $stackData = json_decode((string)$stackRow['data'], true);
            if (!is_array($stackData)) {
                $stackData = [];
            }

            $standardsData = [];
            if ($standardsRow !== false) {
                $standardsData = json_decode((string)$standardsRow['data'], true);
                if (!is_array($standardsData)) {
                    $standardsData = [];
                }
            }

            if (array_is_list($stackData)) {
                $stackData = ['items' => $stackData];
            }

            if (!isset($stackData['title']) && ($standardsData['title'] ?? '') !== '') {
                $stackData['title'] = $standardsData['title'];
            }
            if (!isset($stackData['text']) && ($standardsData['text'] ?? '') !== '') {
                $stackData['text'] = $standardsData['text'];
            }

            $this->update('{{%content_blocks}}', [
                'data' => json_encode($stackData, JSON_UNESCAPED_UNICODE),
                'block_type' => BlockTypeRegistry::TYPE_STACK_SECTION,
                'updated_at' => $now,
            ], ['id' => $stackRow['id']]);
        }

        $this->delete('{{%content_blocks}}', [
            'page_id' => $pageId,
            'block_key' => ['standards', 'ethics'],
        ]);

        $order = [
            'seo' => 0,
            'hero' => 1,
            'intro' => 2,
            'comfort' => 3,
            'stack' => 4,
        ];

        foreach ($order as $blockKey => $sortOrder) {
            $this->update(
                '{{%content_blocks}}',
                ['sort_order' => $sortOrder, 'updated_at' => $now],
                ['page_id' => $pageId, 'block_key' => $blockKey]
            );
        }
    }

    public function safeDown(): void
    {
        // Структура stack и удалённые блоки не восстанавливаются автоматически.
    }
}
