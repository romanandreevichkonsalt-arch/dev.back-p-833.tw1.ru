<?php

use yii\db\Migration;

class m260810_180000_redetect_content_block_types extends Migration
{
    public function safeUp(): void
    {
        $blocks = (new \yii\db\Query())
            ->select(['cb.id', 'cb.block_key', 'cb.data', 'cp.slug'])
            ->from(['cb' => '{{%content_blocks}}'])
            ->innerJoin(['cp' => '{{%content_pages}}'], 'cp.id = cb.page_id')
            ->all();

        foreach ($blocks as $row) {
            $data = json_decode((string)$row['data'], true);
            if (!is_array($data)) {
                $data = [];
            }

            $type = \app\services\content\BlockTypeRegistry::detect(
                (string)$row['slug'],
                (string)$row['block_key'],
                $data
            );

            $this->update('{{%content_blocks}}', ['block_type' => $type], ['id' => $row['id']]);
        }
    }

    public function safeDown(): void
    {
        // Типы блоков не восстанавливаются автоматически.
    }
}
