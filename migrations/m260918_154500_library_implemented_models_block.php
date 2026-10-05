<?php

use app\services\content\BlockTypeRegistry;
use yii\db\Migration;
use yii\db\Query;

class m260918_154500_library_implemented_models_block extends Migration
{
    /**
     * @return array<string, mixed>
     */
    private function defaultImplementedModelsData(): array
    {
        return [
            'title' => 'Реализованные идеи',
            'description' => 'Материал по-настоящему раскрывается только в интерьере. Здесь — дизайнерские проекты, '
                . 'в которых использованы ткани из нашей библиотеки',
            'items' => [
                [
                    'author' => 'Пушной Александр',
                    'title' => 'Кресло 3',
                    'projectName' => 'Silver',
                    'image' => ['src' => '', 'alt' => 'Кресло 3'],
                ],
                [
                    'author' => 'Васильев Александр',
                    'title' => 'Диван 3 и кресло 1',
                    'projectName' => 'Monolith',
                    'image' => ['src' => '', 'alt' => 'Диван и кресло'],
                ],
                [
                    'author' => 'Пушной Александр',
                    'title' => 'Кресло 3',
                    'projectName' => 'Silver',
                    'image' => ['src' => '', 'alt' => 'Кресло 3'],
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
            ->where(['page_id' => $pageId, 'block_key' => 'implementedModels'])
            ->exists();

        if (!$exists) {
            $this->insert('{{%content_blocks}}', [
                'page_id' => $pageId,
                'block_key' => 'implementedModels',
                'block_type' => BlockTypeRegistry::TYPE_LIBRARY_IMPLEMENTED_MODELS,
                'data' => json_encode($this->defaultImplementedModelsData(), JSON_UNESCAPED_UNICODE),
                'sort_order' => 2,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $this->update(
            '{{%content_blocks}}',
            ['sort_order' => 0, 'updated_at' => $now],
            ['page_id' => $pageId, 'block_key' => 'seo']
        );
        $this->update(
            '{{%content_blocks}}',
            ['sort_order' => 1, 'updated_at' => $now],
            ['page_id' => $pageId, 'block_key' => 'hero']
        );
        $this->update(
            '{{%content_blocks}}',
            ['sort_order' => 2, 'updated_at' => $now],
            ['page_id' => $pageId, 'block_key' => 'implementedModels']
        );
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
            'block_key' => 'implementedModels',
        ]);
    }
}
