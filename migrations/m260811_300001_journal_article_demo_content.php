<?php

use yii\db\Migration;
use yii\db\Query;

class m260811_300001_journal_article_demo_content extends Migration
{
    public function safeUp(): void
    {
        $now = date('Y-m-d H:i:s');

        $previewSrc = 'https://dev.back-p-833.tw1.ru/images/journal/geometriya-komforta.webp';
        $detailSrc = 'https://dev.back-p-833.tw1.ru/images/journal/geometriya-komforta-detail.webp';

        $blocks = [
            [
                'type' => 'image',
                'image' => [
                    'src' => $previewSrc,
                    'alt' => 'Интерьер с диваном коллекции A+',
                ],
                'caption' => '«Русская скульптура и интерьер — источник вдохновения для новой коллекции»',
            ],
            [
                'type' => 'text',
                'text' => 'Коллекция A+ родилась из идеи геометрии комфорта: чистые линии, мягкие пропорции и фактура, которая остаётся актуальной в разных интерьерных сценах. Мы собрали в одной серии всё, что делает диван не просто мебелью, а архитектурным элементом пространства.',
            ],
            [
                'type' => 'text',
                'text' => 'На этапе разработки мы тестировали сочетания обивки и каркаса, проверяли эргономику посадки и визуальный баланс в реальных проектах. Так появилась коллекция, в которой эстетика и функциональность работают в равной мере.',
            ],
            [
                'type' => 'divider',
            ],
            [
                'type' => 'quote',
                'text' => 'Прекрасный интерьер начинается с правильной геометрии и честных материалов.',
                'author' => 'ведущий дизайнер бренда',
            ],
            [
                'type' => 'text',
                'text' => 'В коллекции A+ каждая деталь — от подлокотника до стежка — проходит отдельную проверку. Мы сознательно оставили форму лаконичной, чтобы интерьер мог говорить через фактуру и цвет.',
            ],
            [
                'type' => 'image',
                'image' => [
                    'src' => $detailSrc,
                    'alt' => 'Деталь обивки коллекции A+',
                ],
            ],
            [
                'type' => 'divider',
            ],
            [
                'type' => 'heading',
                'level' => 2,
                'text' => 'Детали коллекции A+',
            ],
            [
                'type' => 'text',
                'text' => 'Ниже — ключевые элементы, которые определяют характер коллекции: фактура ткани, геометрия каркаса и визуальная лёгкость модулей.',
            ],
            [
                'type' => 'gallery',
                'columns' => 2,
                'images' => [
                    [
                        'src' => $previewSrc,
                        'alt' => 'Модуль дивана A+',
                    ],
                    [
                        'src' => $detailSrc,
                        'alt' => 'Фактура обивки A+',
                    ],
                ],
            ],
        ];

        $this->insert('{{%journal_articles}}', [
            'slug' => 'geometriya-komforta',
            'category_id' => 'process',
            'title' => 'Геометрия комфорта: как создавалась новая коллекция A+',
            'date' => '07.10.2025',
            'reading_time' => '5 мин',
            'excerpt' => '',
            'tall' => false,
            'image_src' => $previewSrc,
            'image_alt' => 'Коллекция A+',
            'image_position' => 'center center',
            'seo_title' => 'Геометрия комфорта: коллекция A+ — Журнал МФ Анна',
            'seo_description' => 'Как создавалась новая коллекция A+: эстетика, фактуры и архитектура комфорта.',
            'blocks' => json_encode($blocks, JSON_UNESCAPED_UNICODE),
            'sort_order' => 0,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->clearCategoriesItems();
    }

    public function safeDown(): void
    {
        $this->delete('{{%journal_articles}}', ['slug' => 'geometriya-komforta']);
    }

    private function clearCategoriesItems(): void
    {
        $pageId = (new Query())
            ->select('id')
            ->from('{{%content_pages}}')
            ->where(['slug' => 'journal'])
            ->scalar();

        if ($pageId === false) {
            return;
        }

        $row = (new Query())
            ->select(['id', 'data'])
            ->from('{{%content_blocks}}')
            ->where(['page_id' => (int)$pageId, 'block_key' => 'categories'])
            ->one();

        if ($row === false) {
            return;
        }

        $data = json_decode((string)$row['data'], true);
        if (!is_array($data)) {
            return;
        }

        foreach ($data as &$category) {
            if (is_array($category)) {
                $category['items'] = [];
            }
        }
        unset($category);

        $this->update('{{%content_blocks}}', [
            'data' => json_encode($data, JSON_UNESCAPED_UNICODE),
        ], ['id' => $row['id']]);
    }
}
