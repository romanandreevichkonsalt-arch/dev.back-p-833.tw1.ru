<?php

use yii\db\Migration;
use yii\db\Query;

class m260811_290000_journal_hero_tabs extends Migration
{
    public function safeUp(): void
    {
        $pageId = (new Query())
            ->select('id')
            ->from('{{%content_pages}}')
            ->where(['slug' => 'journal'])
            ->scalar();

        if ($pageId === false) {
            return;
        }

        $pageId = (int)$pageId;

        $this->updateHero($pageId);
        $this->updateCategories($pageId);
    }

    public function safeDown(): void
    {
        // Структура journal не восстанавливается автоматически.
    }

    private function updateHero(int $pageId): void
    {
        $row = (new Query())
            ->select(['id', 'data'])
            ->from('{{%content_blocks}}')
            ->where(['page_id' => $pageId, 'block_key' => 'hero'])
            ->one();

        if ($row === false) {
            return;
        }

        $data = json_decode((string)$row['data'], true);
        if (!is_array($data)) {
            $data = [];
        }

        if (isset($data['image']) && is_array($data['image']) && !isset($data['imageDesktop'])) {
            $data['imageDesktop'] = $data['image'];
            $data['imageMobile'] = $data['imageMobile'] ?? $data['image'];
        }

        $defaults = [
            'title' => 'МФ АННА',
            'subtitle' => 'Как создаётся архитектура комфорта: эстетика, фактуры, проекты',
            'imageDesktop' => [
                'src' => 'https://dev.back-p-833.tw1.ru/images/journal/hero.webp',
                'alt' => 'Журнал',
            ],
            'imageMobile' => [
                'src' => 'https://dev.back-p-833.tw1.ru/images/journal/hero.webp',
                'alt' => 'Журнал',
            ],
        ];

        foreach ($defaults as $key => $value) {
            if (!isset($data[$key]) || $data[$key] === '' || $data[$key] === []) {
                $data[$key] = $value;
            }
        }

        $this->update('{{%content_blocks}}', [
            'data' => json_encode($data, JSON_UNESCAPED_UNICODE),
            'block_type' => \app\services\content\BlockTypeRegistry::TYPE_HERO_MEDIA,
        ], ['id' => $row['id']]);
    }

    private function updateCategories(int $pageId): void
    {
        $row = (new Query())
            ->select(['id', 'data'])
            ->from('{{%content_blocks}}')
            ->where(['page_id' => $pageId, 'block_key' => 'categories'])
            ->one();

        if ($row === false) {
            return;
        }

        $data = json_decode((string)$row['data'], true);
        if (!is_array($data)) {
            $data = [];
        }

        $templates = $this->defaultCategories();
        $byId = [];

        foreach ($data as $cat) {
            if (!is_array($cat)) {
                continue;
            }
            $id = trim((string)($cat['id'] ?? ''));
            if ($id !== '') {
                $byId[$id] = $cat;
            }
        }

        $merged = [];
        foreach ($templates as $template) {
            $id = $template['id'];
            $existing = $byId[$id] ?? null;

            if ($existing !== null) {
                $merged[] = array_merge($template, [
                    'label' => trim((string)($existing['label'] ?? '')) ?: $template['label'],
                    'icon' => trim((string)($existing['icon'] ?? '')) ?: $template['icon'],
                    'items' => is_array($existing['items'] ?? null) ? $existing['items'] : [],
                ]);
                continue;
            }

            $merged[] = $template;
        }

        $this->update('{{%content_blocks}}', [
            'data' => json_encode($merged, JSON_UNESCAPED_UNICODE),
            'block_type' => \app\services\content\BlockTypeRegistry::TYPE_JOURNAL_CATEGORIES,
        ], ['id' => $row['id']]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function defaultCategories(): array
    {
        return [
            [
                'id' => 'process',
                'label' => 'Архитектура процессов',
                'icon' => 'journal-process',
                'items' => [],
            ],
            [
                'id' => 'interior',
                'label' => 'Интерьерные проекты',
                'icon' => 'journal-interior',
                'items' => [],
            ],
            [
                'id' => 'textures',
                'label' => 'Исследование фактур',
                'icon' => 'journal-textures',
                'items' => [],
            ],
            [
                'id' => 'interviews',
                'label' => 'Интервью с дизайнерами и командой',
                'icon' => 'journal-interviews',
                'items' => [],
            ],
        ];
    }
}
