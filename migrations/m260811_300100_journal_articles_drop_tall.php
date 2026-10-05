<?php

use yii\db\Migration;

class m260811_300100_journal_articles_drop_tall extends Migration
{
    public function safeUp(): void
    {
        $this->dropColumn('{{%journal_articles}}', 'tall');
    }

    public function safeDown(): void
    {
        $this->addColumn('{{%journal_articles}}', 'tall', $this->boolean()->notNull()->defaultValue(false));
    }
}
