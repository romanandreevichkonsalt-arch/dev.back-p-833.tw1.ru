<?php

use app\services\content\BlockTypeRegistry;
use app\services\content\HeroBannerPayload;
use yii\db\Migration;

class m260813_180000_unify_hero_banner_payload extends Migration
{
    public function safeUp(): void
    {
        $rows = (new \yii\db\Query())
            ->from('{{%content_blocks}}')
            ->where(['block_type' => [BlockTypeRegistry::TYPE_HERO_MEDIA, BlockTypeRegistry::TYPE_HERO_HOME]])
            ->all($this->db);

        foreach ($rows as $row) {
            $data = json_decode((string)$row['data'], true);
            if (!is_array($data)) {
                continue;
            }

            $normalized = HeroBannerPayload::normalize($data);
            $this->update(
                '{{%content_blocks}}',
                ['data' => json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)],
                ['id' => $row['id']]
            );
        }
    }

    public function safeDown(): bool
    {
        echo "m260813_180000_unify_hero_banner_payload cannot be reverted.\n";

        return false;
    }
}
