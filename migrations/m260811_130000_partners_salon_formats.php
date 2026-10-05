<?php

use yii\db\Migration;
use yii\db\Query;

class m260811_130000_partners_salon_formats extends Migration
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

        $exists = (new Query())
            ->from('{{%content_blocks}}')
            ->where(['page_id' => $pageId, 'block_key' => 'salonFormats'])
            ->exists();

        if ($exists) {
            return;
        }

        $audienceOrder = (new Query())
            ->select('sort_order')
            ->from('{{%content_blocks}}')
            ->where(['page_id' => $pageId, 'block_key' => 'audience'])
            ->scalar();

        $insertOrder = $audienceOrder !== false ? (int)$audienceOrder + 1 : 5;

        $this->db->createCommand()
            ->update(
                '{{%content_blocks}}',
                ['sort_order' => new \yii\db\Expression('sort_order + 1')],
                ['and', ['page_id' => $pageId], ['>', 'sort_order', (int)$audienceOrder]]
            )
            ->execute();

        $data = $this->defaultSalonFormatsSection();
        $type = \app\services\content\BlockTypeRegistry::detect('partners', 'salonFormats', $data);
        $now = date('Y-m-d H:i:s');

        $this->insert('{{%content_blocks}}', [
            'page_id' => $pageId,
            'block_key' => 'salonFormats',
            'block_type' => $type,
            'data' => json_encode($data, JSON_UNESCAPED_UNICODE),
            'sort_order' => $insertOrder,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function safeDown(): void
    {
        $pageId = (new Query())
            ->select('id')
            ->from('{{%content_pages}}')
            ->where(['slug' => 'partners'])
            ->scalar();

        if ($pageId === false) {
            return;
        }

        $this->delete('{{%content_blocks}}', [
            'page_id' => $pageId,
            'block_key' => 'salonFormats',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultSalonFormatsSection(): array
    {
        return [
            'title' => 'Форматы партнёрства',
            'subtitle' => 'Выберите формат салона под ваши инвестиции, площадь и планируемый объём продаж',
            'conditionsTitle' => 'Общие условия партнёрства:',
            'items' => [
                [
                    'id' => 'start',
                    'title' => 'Старт',
                    'text' => 'Оптимальный формат для первого салона и быстрого старта в мебельном бизнесе',
                    'area' => '70–100 м²',
                    'assortment' => '8–10 моделей',
                    'profit' => 'от 200 000 ₽',
                    'employees' => '1-2',
                    'investment' => '750 000 ₽',
                ],
                [
                    'id' => 'comfort',
                    'title' => 'Комфорт',
                    'text' => 'Баланс между ассортиментом, прибылью и инвестициями',
                    'area' => '100–150 м²',
                    'assortment' => '11–13 моделей',
                    'profit' => 'от 250 000 ₽',
                    'employees' => '2',
                    'investment' => '1 000 000 ₽',
                ],
                [
                    'id' => 'vip',
                    'title' => 'ВИП',
                    'text' => 'Максимальные возможности и премиальный уровень дохода',
                    'area' => '150–200 м²',
                    'assortment' => '15–17 моделей',
                    'profit' => 'от 350 000 ₽',
                    'employees' => '2 и более',
                    'investment' => '1 500 000 ₽',
                ],
            ],
            'conditions' => [
                ['label' => 'Паушальный взнос', 'value' => '0 ₽'],
                ['label' => 'Роялти', 'value' => '0 ₽'],
                ['label' => 'Срок окупаемости', 'value' => 'Около 4 месяцев'],
            ],
        ];
    }
}
