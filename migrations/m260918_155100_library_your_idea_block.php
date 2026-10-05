<?php

use app\services\content\BlockTypeRegistry;
use yii\db\Migration;
use yii\db\Query;

class m260918_155100_library_your_idea_block extends Migration
{
    /**
     * @return array<string, mixed>
     */
    private function defaultYourIdeaData(): array
    {
        return [
            'title' => 'Ваша идея — наше исполнение',
            'text' => 'Мебель — лишь часть проекта. Мы помогаем дизайнерам воплощать идеи: подбираем фактуры из библиотеки, '
                . 'согласуем детали и производим изделия под ваш замысел.',
            'textSecondary' => 'Узнайте больше о форматах работы с фабрикой и условиях партнёрской программы для дизайнеров.',
            'ctaLabel' => 'Получить предложение',
            'ctaHref' => '/designers',
            'image' => ['src' => '', 'alt' => 'Интерьер с мебелью'],
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
            ->where(['page_id' => $pageId, 'block_key' => 'yourIdea'])
            ->exists();

        if (!$exists) {
            $this->insert('{{%content_blocks}}', [
                'page_id' => $pageId,
                'block_key' => 'yourIdea',
                'block_type' => BlockTypeRegistry::TYPE_LIBRARY_YOUR_IDEA,
                'data' => json_encode($this->defaultYourIdeaData(), JSON_UNESCAPED_UNICODE),
                'sort_order' => 3,
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
            'block_key' => 'yourIdea',
        ]);
    }
}
