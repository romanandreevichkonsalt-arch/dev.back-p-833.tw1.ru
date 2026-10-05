<?php

use yii\db\Migration;
use yii\db\Query;

class m260811_140000_partners_presentation_section extends Migration
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
            ->where(['page_id' => $pageId, 'block_key' => 'presentation'])
            ->one();

        if ($row === false) {
            return;
        }

        $data = json_decode((string)$row['data'], true);
        if (!is_array($data)) {
            $data = [];
        }

        if (!isset($data['items']) && !isset($data['image'])) {
            $data = $this->defaultPartnersPresentationSection($data);
        }

        $type = \app\services\content\BlockTypeRegistry::detect('partners', 'presentation', $data);

        $this->update('{{%content_blocks}}', [
            'data' => json_encode($data, JSON_UNESCAPED_UNICODE),
            'block_type' => $type,
        ], ['id' => $row['id']]);
    }

    public function safeDown(): void
    {
        // Структура presentation на partners не восстанавливается автоматически.
    }

    /**
     * @param array<string, mixed> $existing
     * @return array<string, mixed>
     */
    private function defaultPartnersPresentationSection(array $existing): array
    {
        return [
            'title' => $existing['title'] ?? 'Скачайте презентацию и ознакомьтесь с полным перечнем преимуществ для партнёров:',
            'items' => [
                ['number' => '01', 'text' => 'Пошаговый план запуска'],
                ['number' => '02', 'text' => 'Подготовка торговой площади'],
                ['number' => '03', 'text' => 'Страховка первой закупки'],
                ['number' => '04', 'text' => 'Маркетинговая поддержка'],
            ],
            'fileUrl' => $existing['fileUrl'] ?? 'https://dev.back-p-833.tw1.ru/files/partners-presentation.pdf',
            'fileLabel' => $existing['fileLabel'] ?? 'Скачать презентацию',
            'fileHint' => 'PDF, 12 страниц — полный перечень условий и преимуществ',
            'image' => [
                'src' => 'https://dev.back-p-833.tw1.ru/images/partners/presentation-photo.webp',
                'alt' => 'Салон',
            ],
        ];
    }
}
