<?php

use app\services\content\BlockTypeRegistry;
use yii\db\Migration;
use yii\db\Query;

class m260813_193000_building_standards_block extends Migration
{
    /**
     * @return array<string, int>
     */
    private function blockSortOrder(): array
    {
        return [
            'seo' => 0,
            'hero' => 1,
            'intro' => 2,
            'comfort' => 3,
            'standards' => 4,
            'stack' => 5,
            'ethics' => 6,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function defaultStandardsData(): array
    {
        return [
            'title' => 'Этика и стандарты мебельной фабрики «Анна»',
            'text' => 'Фабрика «Анна» развивает культуру ответственности на каждом этапе своей работы. '
                . 'Вырастая из небольшого семейного дела с 30-летней историей, мы сохранили самое ценное — '
                . 'трепетное отношение к людям. Именно поэтому высокие стандарты для нас — не абстрактное понятие, '
                . 'а осязаемый дух бренда, который определяет выбор безопасных технологий '
                . 'и прозрачность партнерских отношений.',
        ];
    }

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

        $exists = (new Query())
            ->from('{{%content_blocks}}')
            ->where(['page_id' => $pageId, 'block_key' => 'standards'])
            ->exists();

        if (!$exists) {
            $this->insert('{{%content_blocks}}', [
                'page_id' => $pageId,
                'block_key' => 'standards',
                'block_type' => BlockTypeRegistry::TYPE_TITLE_TEXT,
                'data' => json_encode($this->defaultStandardsData(), JSON_UNESCAPED_UNICODE),
                'sort_order' => $this->blockSortOrder()['standards'],
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach ($this->blockSortOrder() as $blockKey => $sortOrder) {
            $this->update(
                '{{%content_blocks}}',
                ['sort_order' => $sortOrder, 'updated_at' => $now],
                ['page_id' => $pageId, 'block_key' => $blockKey]
            );
        }
    }

    public function safeDown(): void
    {
        $pageId = (new Query())
            ->select('id')
            ->from('{{%content_pages}}')
            ->where(['slug' => 'building'])
            ->scalar();

        if ($pageId === false) {
            return;
        }

        $this->delete('{{%content_blocks}}', [
            'page_id' => (int)$pageId,
            'block_key' => 'standards',
        ]);
    }
}
