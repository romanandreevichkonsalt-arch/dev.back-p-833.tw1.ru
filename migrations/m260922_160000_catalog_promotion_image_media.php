<?php

use yii\db\Migration;

class m260922_160000_catalog_promotion_image_media extends Migration
{
    public function safeUp(): void
    {
        $schema = $this->db->schema->getTableSchema('{{%catalog_promotions}}', true);
        if ($schema === null || isset($schema->columns['image_media_id'])) {
            return;
        }

        $this->addColumn('{{%catalog_promotions}}', 'image_media_id', $this->integer()->null());

        try {
            $this->addForeignKey(
                'fk_catalog_promotions_image_media_id',
                '{{%catalog_promotions}}',
                'image_media_id',
                '{{%media_files}}',
                'id',
                'SET NULL',
                'CASCADE',
            );
        } catch (\Throwable) {
        }
    }

    public function safeDown(): void
    {
        $schema = $this->db->schema->getTableSchema('{{%catalog_promotions}}', true);
        if ($schema === null || !isset($schema->columns['image_media_id'])) {
            return;
        }

        try {
            $this->dropForeignKey('fk_catalog_promotions_image_media_id', '{{%catalog_promotions}}');
        } catch (\Throwable) {
        }

        $this->dropColumn('{{%catalog_promotions}}', 'image_media_id');
    }
}
