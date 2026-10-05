<?php

use yii\db\Migration;

class m260807_150000_fabric_color_swatch_fields extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn('{{%catalog_fabric_colors}}', 'hex_color', $this->string(7)->null()->after('category'));
        $this->addColumn('{{%catalog_fabric_colors}}', 'swatch_media_id', $this->integer()->null()->after('hex_color'));
        $this->createIndex('idx_catalog_fabric_colors_swatch_media_id', '{{%catalog_fabric_colors}}', 'swatch_media_id');
        $this->addForeignKey(
            'fk_catalog_fabric_colors_swatch_media_id',
            '{{%catalog_fabric_colors}}',
            'swatch_media_id',
            '{{%media_files}}',
            'id',
            'SET NULL',
            'CASCADE'
        );
    }

    public function safeDown(): bool
    {
        $this->dropForeignKey('fk_catalog_fabric_colors_swatch_media_id', '{{%catalog_fabric_colors}}');
        $this->dropIndex('idx_catalog_fabric_colors_swatch_media_id', '{{%catalog_fabric_colors}}');
        $this->dropColumn('{{%catalog_fabric_colors}}', 'swatch_media_id');
        $this->dropColumn('{{%catalog_fabric_colors}}', 'hex_color');

        return true;
    }
}
