<?php

use yii\db\Migration;
use yii\db\Query;

class m260811_124000_partners_audience_section extends Migration
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
            ->select(['id', 'data'])
            ->from('{{%content_blocks}}')
            ->where(['page_id' => $pageId, 'block_key' => 'audience'])
            ->one();

        if ($row === false) {
            return;
        }

        $data = json_decode((string)$row['data'], true);
        if (!is_array($data)) {
            $data = [];
        }

        if (array_is_list($data)) {
            $data = $this->defaultPartnersAudienceSection();
        }

        $type = \app\services\content\BlockTypeRegistry::detect('partners', 'audience', $data);

        $this->update('{{%content_blocks}}', [
            'data' => json_encode($data, JSON_UNESCAPED_UNICODE),
            'block_type' => $type,
        ], ['id' => $row['id']]);
    }

    public function safeDown(): void
    {
        // Структура audience на partners не восстанавливается автоматически.
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultPartnersAudienceSection(): array
    {
        return [
            'title' => 'Кому подойдёт франшиза МФ «Анна»',
            'image' => [
                'src' => 'https://dev.back-p-833.tw1.ru/images/partners/audience-banner.webp',
                'alt' => 'Мебель',
            ],
            'items' => [
                [
                    'number' => '01',
                    'title' => 'Предпринимателям, начинающим путь в мебельной сфере',
                    'text' => 'Надёжный старт с понятной моделью и поддержкой',
                ],
                [
                    'number' => '02',
                    'title' => 'Владельцам торговых площадей и мебельных магазинов',
                    'text' => 'Расширение ассортимента\nУкрепление позиций в сегменте средний+ и премиум\nБезопасный рост',
                ],
                [
                    'number' => '03',
                    'title' => 'Инвесторам, ищущим системный офлайн-бизнес',
                    'text' => 'Вложение в рабочую модель с подтверждённой эффективностью и рентабельностью',
                ],
            ],
            'stats' => [
                ['text' => 'Опыт в мебели: не обязателен'],
                ['text' => 'Команда сотрудников на старте проекта: от 2 человек'],
                ['text' => 'Средняя выручка торговой площади: 20–50 тыс. руб с м²'],
            ],
        ];
    }
}
