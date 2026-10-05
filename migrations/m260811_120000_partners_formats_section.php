<?php

use yii\db\Migration;
use yii\db\Query;

class m260811_120000_partners_formats_section extends Migration
{
    public function safeUp(): void
    {
        $pageId = (new Query())
            ->select('id')
            ->from('{{%content_pages}}')
            ->where(['slug' => 'partners'])
            ->scalar();

        if ($pageId === false) {
            return;
        }

        $row = (new Query())
            ->select(['id', 'data', 'block_type'])
            ->from('{{%content_blocks}}')
            ->where(['page_id' => $pageId, 'block_key' => 'formats'])
            ->one();

        if ($row === false) {
            return;
        }

        $data = json_decode((string)$row['data'], true);
        if (!is_array($data)) {
            $data = [];
        }

        if (array_is_list($data)) {
            $data = $this->defaultPartnersFormatsSection();
        }

        $type = \app\services\content\BlockTypeRegistry::detect('partners', 'formats', $data);

        $this->update('{{%content_blocks}}', [
            'data' => json_encode($data, JSON_UNESCAPED_UNICODE),
            'block_type' => $type,
        ], ['id' => $row['id']]);
    }

    public function safeDown(): void
    {
        // Структура formats на partners не восстанавливается автоматически.
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultPartnersFormatsSection(): array
    {
        return [
            'title' => 'Покупая франшизу, вы становитесь нашим партнёром, а не просто франчайзи',
            'subtitle' => 'Мы запускаем бизнес вместе с вами',
            'lead' => 'Вы получите:',
            'image' => [
                'src' => 'https://dev.back-p-833.tw1.ru/images/partners/formats-photo.webp',
                'alt' => 'Партнёрство',
            ],
            'items' => [
                [
                    'number' => '01',
                    'text' => 'Готовую бизнес-модель, которая предлагает возможность дохода даже в несезон',
                ],
                [
                    'number' => '02',
                    'text' => 'Проверенный ассортимент, стабильные сроки производства и поставок продукции',
                ],
                [
                    'number' => '03',
                    'text' => 'Регламенты продаж и информационную поддержку: брендбук, рекламные материалы, таргетированная реклама в вашем регионе',
                ],
                [
                    'number' => '04',
                    'text' => 'Поддержку запуска и персонального менеджера для старта и менторства в будущем',
                ],
            ],
        ];
    }
}
