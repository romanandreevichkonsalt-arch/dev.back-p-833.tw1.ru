<?php

use yii\db\Migration;
use yii\db\Query;

class m260811_230000_faq_hero_intro_tabs extends Migration
{
    public function safeUp(): void
    {
        $pageId = (new Query())
            ->select('id')
            ->from('{{%content_pages}}')
            ->where(['slug' => 'faq'])
            ->scalar();

        if ($pageId === false) {
            return;
        }

        $pageId = (int)$pageId;

        $this->updateHero($pageId);
        $this->ensureIntroBlock($pageId);
        $this->updateCategories($pageId);
    }

    public function safeDown(): void
    {
        // Структура FAQ не восстанавливается автоматически.
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
            'subtitle' => 'Если у вас появились вопросы о коллекциях, материалах, сроках или условиях сотрудничества — возможно, ответ уже есть в этом разделе',
            'imageDesktop' => [
                'src' => 'https://dev.back-p-833.tw1.ru/images/faq/hero.webp',
                'alt' => 'Частые вопросы',
            ],
            'imageMobile' => [
                'src' => 'https://dev.back-p-833.tw1.ru/images/faq/hero.webp',
                'alt' => 'Частые вопросы',
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

    private function ensureIntroBlock(int $pageId): void
    {
        $exists = (new Query())
            ->from('{{%content_blocks}}')
            ->where(['page_id' => $pageId, 'block_key' => 'intro'])
            ->exists($this->db);

        if ($exists) {
            $row = (new Query())
                ->select(['id', 'data'])
                ->from('{{%content_blocks}}')
                ->where(['page_id' => $pageId, 'block_key' => 'intro'])
                ->one();

            if ($row !== false) {
                $data = json_decode((string)$row['data'], true);
                if (!is_array($data)) {
                    $data = [];
                }

                $defaults = [
                    'lead' => 'FAQs',
                    'text' => 'Мы собрали для вас ответы на самый часто задаваемые вопросы',
                ];

                foreach ($defaults as $key => $value) {
                    if (!isset($data[$key]) || trim((string)$data[$key]) === '') {
                        $data[$key] = $value;
                    }
                }

                $this->update('{{%content_blocks}}', [
                    'data' => json_encode($data, JSON_UNESCAPED_UNICODE),
                    'block_type' => \app\services\content\BlockTypeRegistry::TYPE_INTRO,
                ], ['id' => $row['id']]);
            }

            return;
        }

        $heroSort = (new Query())
            ->select('sort_order')
            ->from('{{%content_blocks}}')
            ->where(['page_id' => $pageId, 'block_key' => 'hero'])
            ->scalar();

        $sortOrder = $heroSort !== false ? (int)$heroSort + 1 : 2;
        $now = date('Y-m-d H:i:s');

        $this->insert('{{%content_blocks}}', [
            'page_id' => $pageId,
            'block_key' => 'intro',
            'block_type' => \app\services\content\BlockTypeRegistry::TYPE_INTRO,
            'data' => json_encode([
                'lead' => 'FAQs',
                'text' => 'Мы собрали для вас ответы на самый часто задаваемые вопросы',
            ], JSON_UNESCAPED_UNICODE),
            'sort_order' => $sortOrder,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
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
            'block_type' => \app\services\content\BlockTypeRegistry::TYPE_FAQ_CATEGORIES,
        ], ['id' => $row['id']]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function defaultCategories(): array
    {
        $sampleItem = [
            'question' => 'Как выбрать подходящую модель?',
            'paragraphs' => [
                'Посетите наш салон или свяжитесь с менеджером для консультации.',
                [
                    ['t' => 'Подробнее на странице '],
                    ['t' => 'Контакты', 'to' => '/contacts', 's' => 'link'],
                    ['t' => '.'],
                ],
            ],
            'footnote' => '* Сроки изготовления зависят от модели',
        ];

        return [
            [
                'id' => 'order',
                'label' => 'Заказ и подбор мебели',
                'icon' => 'faq-order',
                'items' => [$sampleItem],
            ],
            [
                'id' => 'payment',
                'label' => 'Оплата и доставка',
                'icon' => 'faq-payment',
                'items' => [],
            ],
            [
                'id' => 'materials',
                'label' => 'Материалы и уход',
                'icon' => 'faq-materials',
                'items' => [],
            ],
            [
                'id' => 'cooperation',
                'label' => 'Сотрудничество',
                'icon' => 'faq-cooperation',
                'items' => [],
            ],
        ];
    }
}
