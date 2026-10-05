<?php

use yii\db\Migration;

class m260819_180000_catalog_model_fitting_room_url extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn(
            '{{%catalog_models}}',
            'fitting_room_url',
            $this->string(512)->null()->after('video_id')
        );
    }

    public function safeDown(): void
    {
        $this->dropColumn('{{%catalog_models}}', 'fitting_room_url');
    }
}
