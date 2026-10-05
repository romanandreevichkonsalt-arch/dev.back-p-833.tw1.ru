<?php

use yii\db\Migration;

class m261002_200000_media_listing_frame extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn('{{%media_files}}', 'listing_frame_json', $this->text()->null()->after('height'));
        $this->addColumn(
            '{{%media_files}}',
            'listing_frame_locked',
            $this->boolean()->notNull()->defaultValue(false)->after('listing_frame_json')
        );
    }

    public function safeDown(): void
    {
        $this->dropColumn('{{%media_files}}', 'listing_frame_locked');
        $this->dropColumn('{{%media_files}}', 'listing_frame_json');
    }
}
