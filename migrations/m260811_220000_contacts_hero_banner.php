<?php

use yii\db\Migration;
use yii\db\Query;

class m260811_220000_contacts_hero_banner extends Migration
{
    public function safeUp(): void
    {
        $pageId = (new Query())
            ->select('id')
            ->from('{{%content_pages}}')
            ->where(['slug' => 'contacts'])
            ->scalar();

        if ($pageId === false) {
            return;
        }

        $this->updateHero((int)$pageId);
    }

    public function safeDown(): void
    {
        // Структура contacts hero не восстанавливается автоматически.
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
            'subtitle' => 'Если нужна консультация, помощь в выборе мебели или информация о сотрудничестве — мы на связи',
            'imageDesktop' => [
                'src' => 'https://dev.back-p-833.tw1.ru/images/contacts/hero.webp',
                'alt' => 'Контакты',
            ],
            'imageMobile' => [
                'src' => 'https://dev.back-p-833.tw1.ru/images/contacts/hero.webp',
                'alt' => 'Контакты',
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
}
