<?php

use yii\db\Migration;

class m260929_180000_catalog_model_tech_photos_folder_url extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn(
            '{{%catalog_models}}',
            'tech_photos_folder_url',
            $this->string(512)->null()->after('file_3d_url')
        );
    }

    public function safeDown(): void
    {
        $this->dropColumn('{{%catalog_models}}', 'tech_photos_folder_url');
    }
}
