<?php

use yii\db\Migration;
use yii\db\Query;

class m260811_170000_designers_hero_intro extends Migration
{
    public function safeUp(): void
    {
        $pageId = (new Query())
            ->select('id')
            ->from('{{%content_pages}}')
            ->where(['slug' => 'designers'])
            ->scalar();

        if ($pageId === false) {
            return;
        }

        $this->updateHero((int)$pageId);
        $this->updateIntro((int)$pageId);
        $this->ensureMissionBlock((int)$pageId);
    }

    public function safeDown(): void
    {
        // Структура designers не восстанавливается автоматически.
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
            'subtitle' => 'Культура сотрудничества и поддержки: от образцов материалов до участия в масштабных проектах',
            'imageDesktop' => [
                'src' => 'https://dev.back-p-833.tw1.ru/images/designers/hero.webp',
                'alt' => 'Дизайнерам',
            ],
            'imageMobile' => [
                'src' => 'https://dev.back-p-833.tw1.ru/images/designers/hero.webp',
                'alt' => 'Дизайнерам',
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

    private function updateIntro(int $pageId): void
    {
        $row = (new Query())
            ->select(['id', 'data'])
            ->from('{{%content_blocks}}')
            ->where(['page_id' => $pageId, 'block_key' => 'intro'])
            ->one();

        if ($row === false) {
            return;
        }

        $data = json_decode((string)$row['data'], true);
        if (!is_array($data)) {
            $data = [];
        }

        if (!isset($data['text']) || trim((string)$data['text']) === '') {
            $lead = trim((string)($data['lead'] ?? ''));
            $text = trim((string)($data['text'] ?? ''));
            $data['text'] = $text !== '' ? $text : ($lead !== '' ? $lead : $this->defaultIntroText());
        }

        if (!isset($data['image']) || !is_array($data['image'])) {
            $data['image'] = [
                'src' => 'https://dev.back-p-833.tw1.ru/images/designers/intro-photo.webp',
                'alt' => 'Интерьер',
            ];
        }

        unset($data['lead']);

        $this->update('{{%content_blocks}}', [
            'data' => json_encode($data, JSON_UNESCAPED_UNICODE),
            'block_type' => \app\services\content\BlockTypeRegistry::TYPE_INTRO,
        ], ['id' => $row['id']]);
    }

    private function ensureMissionBlock(int $pageId): void
    {
        $exists = (new Query())
            ->from('{{%content_blocks}}')
            ->where(['page_id' => $pageId, 'block_key' => 'mission'])
            ->exists($this->db);

        if ($exists) {
            return;
        }

        $introSort = (new Query())
            ->select('sort_order')
            ->from('{{%content_blocks}}')
            ->where(['page_id' => $pageId, 'block_key' => 'intro'])
            ->scalar();

        $sortOrder = $introSort !== false ? (int)$introSort + 1 : 3;
        $data = ['items' => $this->defaultMissionItems()];
        $now = date('Y-m-d H:i:s');

        $this->insert('{{%content_blocks}}', [
            'page_id' => $pageId,
            'block_key' => 'mission',
            'block_type' => \app\services\content\BlockTypeRegistry::TYPE_MISSION,
            'data' => json_encode($data, JSON_UNESCAPED_UNICODE),
            'sort_order' => $sortOrder,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function defaultIntroText(): string
    {
        return 'Мы говорим с дизайнерами на одном языке, понимаем их потребности и ценим точность авторского видения. '
            . 'Если вы дизайнер интерьера, мы готовы стать вашим надёжным производственным партнёром, '
            . 'предоставляя инструменты и возможности для создания ваших пространств';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function defaultMissionItems(): array
    {
        return [
            [
                'title' => 'Техническая экспертиза и широкая вариативность моделей',
                'text' => 'Предложим лучшее решение для вашего проекта, учитывая эргономику и конструктив мебели',
                'image' => [
                    'src' => 'https://dev.back-p-833.tw1.ru/images/designers/gallery-1.webp',
                    'alt' => 'Экспертиза',
                ],
            ],
            [
                'title' => '3D-модели и библиотека материалов',
                'text' => 'Готовые модели мебели и актуальные каталоги тканей для проектирования',
                'image' => [
                    'src' => 'https://dev.back-p-833.tw1.ru/images/designers/gallery-1.webp',
                    'alt' => 'Материалы',
                ],
            ],
            [
                'title' => 'Образцы материалов',
                'text' => 'Бесплатная доставка образцов тканей и материалов для ваших проектов',
                'image' => [
                    'src' => 'https://dev.back-p-833.tw1.ru/images/designers/samples.webp',
                    'alt' => 'Образцы',
                ],
            ],
            [
                'title' => 'Персональная поддержка',
                'text' => 'Технические консультации и помощь с подбором и комплектацией',
                'image' => [
                    'src' => 'https://dev.back-p-833.tw1.ru/images/designers/stack-1.webp',
                    'alt' => 'Поддержка',
                ],
            ],
        ];
    }
}
