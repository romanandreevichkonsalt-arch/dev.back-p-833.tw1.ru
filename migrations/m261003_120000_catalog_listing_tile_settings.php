<?php

use yii\db\Migration;

class m261003_120000_catalog_listing_tile_settings extends Migration
{
    public function safeUp(): void
    {
        if ($this->db->getTableSchema('{{%catalog_listing_tile_settings}}', true)) {
            return;
        }

        $this->createTable('{{%catalog_listing_tile_settings}}', [
            'id' => $this->primaryKey(),
            'floor_guide_from_bottom' => $this->integer()->notNull()->defaultValue(75),
            'updated_at' => $this->dateTime()->notNull(),
        ]);

        $this->insert('{{%catalog_listing_tile_settings}}', [
            'floor_guide_from_bottom' => 75,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function safeDown(): void
    {
        if ($this->db->getTableSchema('{{%catalog_listing_tile_settings}}', true)) {
            $this->dropTable('{{%catalog_listing_tile_settings}}');
        }
    }
}
