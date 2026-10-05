<?php

use yii\db\Migration;
use yii\db\Query;

class m260811_160000_partners_contact extends Migration
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
            ->where(['page_id' => $pageId, 'block_key' => 'contact'])
            ->one();

        if ($row === false) {
            return;
        }

        $data = json_decode((string)$row['data'], true);
        if (!is_array($data)) {
            $data = [];
        }

        $data = $this->mergePartnersContactDefaults($data);

        $this->update('{{%content_blocks}}', [
            'data' => json_encode($data, JSON_UNESCAPED_UNICODE),
            'block_type' => \app\services\content\BlockTypeRegistry::TYPE_CONTACT_CTA,
        ], ['id' => $row['id']]);
    }

    public function safeDown(): void
    {
        // Данные contact на partners не восстанавливаются автоматически.
    }

    /**
     * @param array<string, mixed> $existing
     * @return array<string, mixed>
     */
    private function mergePartnersContactDefaults(array $existing): array
    {
        $defaults = [
            'title' => 'Оставьте заявку и получите расчёт под ваш регион',
            'subtitle' => 'Мы проведём с вами онлайн-встречу и подготовим план запуска',
            'image' => [
                'src' => 'https://dev.back-p-833.tw1.ru/images/partners/contact-photo.webp',
                'alt' => 'Салон',
            ],
            'privacyPolicyUrl' => 'https://dev.back-p-833.tw1.ru/files/privacy-policy.pdf',
        ];

        foreach ($defaults as $key => $value) {
            if (!isset($existing[$key]) || $existing[$key] === '' || $existing[$key] === []) {
                $existing[$key] = $value;
            }
        }

        if (isset($existing['image']) && is_array($existing['image'])) {
            $existing['image']['alt'] = $existing['image']['alt'] ?? ($defaults['image']['alt'] ?? '');
        }

        return $existing;
    }
}
