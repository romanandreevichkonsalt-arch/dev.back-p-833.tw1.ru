<?php

use yii\db\Migration;
use yii\db\Query;

class m260811_280000_faq_remove_footnotes extends Migration
{
    public function safeUp(): void
    {
        $pageId = (new Query())
            ->select('id')
            ->from('{{%content_pages}}')
            ->where(['slug' => 'faq'])
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

        foreach ($data as $ci => $category) {
            if (!is_array($category) || !isset($category['items']) || !is_array($category['items'])) {
                continue;
            }

            foreach ($category['items'] as $ii => $item) {
                if (!is_array($item)) {
                    continue;
                }

                $footnote = trim((string)($item['footnote'] ?? ''));
                if ($footnote !== '') {
                    $paragraphs = $item['paragraphs'] ?? [];
                    if (!is_array($paragraphs)) {
                        $paragraphs = [];
                    }

                    if ($paragraphs === []) {
                        $paragraphs = [$footnote];
                    } elseif (is_string($paragraphs[0] ?? null)) {
                        $paragraphs[0] = trim((string)$paragraphs[0]);
                        $paragraphs[] = $footnote;
                    } else {
                        $paragraphs[] = $footnote;
                    }

                    $item['paragraphs'] = $paragraphs;
                }

                unset($item['footnote']);
                $category['items'][$ii] = $item;
            }

            $data[$ci] = $category;
        }

        $this->update('{{%content_blocks}}', [
            'data' => json_encode($data, JSON_UNESCAPED_UNICODE),
        ], ['id' => $row['id']]);
    }

    public function safeDown(): void
    {
        // Сноски FAQ не восстанавливаются автоматически.
    }
}
