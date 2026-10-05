<?php

use yii\db\Migration;

class m260808_120000_media_image_variants extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn('{{%media_files}}', 'path_medium', $this->string(512)->null()->after('path'));
        $this->addColumn('{{%media_files}}', 'path_mini', $this->string(512)->null()->after('path_medium'));
    }

    public function safeDown(): void
    {
        $this->dropColumn('{{%media_files}}', 'path_mini');
        $this->dropColumn('{{%media_files}}', 'path_medium');
    }
}
