<?php

use yii\db\Migration;
use yii\db\Query;

class m260811_200000_designers_block_order extends Migration
{
    public function safeUp(): void
    {
        $pageId = (new Query())
            ->select('id')
            ->from('{{%content_pages}}')
            ->where(['slug' => 'designers'])
            ->scalar();

        if ($pageId === false) {
            return;
        }

        $materials = (new Query())
            ->select(['id', 'sort_order'])
            ->from('{{%content_blocks}}')
            ->where(['page_id' => $pageId, 'block_key' => 'materials'])
            ->one();

        $gallery = (new Query())
            ->select(['id', 'sort_order'])
            ->from('{{%content_blocks}}')
            ->where(['page_id' => $pageId, 'block_key' => 'gallery'])
            ->one();

        if ($materials === false || $gallery === false) {
            return;
        }

        $materialsOrder = (int)$materials['sort_order'];
        $galleryOrder = (int)$gallery['sort_order'];

        if ($materialsOrder === $galleryOrder) {
            $materialsOrder = $galleryOrder + 1;
        }

        $this->update('{{%content_blocks}}', ['sort_order' => $galleryOrder], ['id' => $materials['id']]);
        $this->update('{{%content_blocks}}', ['sort_order' => $materialsOrder], ['id' => $gallery['id']]);
    }

    public function safeDown(): void
    {
        // Порядок блоков не восстанавливается автоматически.
    }
}
