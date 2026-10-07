<?php

use app\services\journal\JournalArticleMarkdownSerializer;
use yii\db\Migration;
use yii\db\Query;

class m261007_164500_journal_article_body_markdown extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn(
            '{{%journal_articles}}',
            'body_markdown',
            $this->text()->after('blocks')
        );

        $this->update('{{%journal_articles}}', ['body_markdown' => '']);

        $rows = (new Query())
            ->select(['id', 'blocks'])
            ->from('{{%journal_articles}}')
            ->all();

        foreach ($rows as $row) {
            $blocks = json_decode((string)$row['blocks'], true);
            if (!is_array($blocks) || $blocks === []) {
                continue;
            }

            $markdown = JournalArticleMarkdownSerializer::fromBlocks($blocks);
            if ($markdown === '') {
                continue;
            }

            $this->update('{{%journal_articles}}', [
                'body_markdown' => $markdown,
            ], ['id' => (int)$row['id']]);
        }
    }

    public function safeDown(): void
    {
        $this->dropColumn('{{%journal_articles}}', 'body_markdown');
    }
}
