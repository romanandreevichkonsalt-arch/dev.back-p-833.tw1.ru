<?php

use yii\db\Migration;
use yii\db\Query;

class m261020_120000_catalog_listing_banners extends Migration
{
    public function safeUp(): void
    {
        if (!$this->db->getTableSchema('{{%catalog_listing_banners}}', true)) {
            $this->createTable('{{%catalog_listing_banners}}', [
                'id' => $this->primaryKey(),
                'direction_id' => $this->integer()->notNull(),
                'category_id' => $this->integer()->notNull()->defaultValue(0),
                'subcategory_id' => $this->integer()->notNull()->defaultValue(0),
                'seo_data' => $this->json()->notNull(),
                'hero_data' => $this->json()->notNull(),
                'created_at' => $this->dateTime()->notNull(),
                'updated_at' => $this->dateTime()->notNull(),
            ]);
            $this->createIndex(
                'ux_catalog_listing_banners_scope',
                '{{%catalog_listing_banners}}',
                ['direction_id', 'category_id', 'subcategory_id'],
                true
            );
            $this->addForeignKey(
                'fk_catalog_listing_banners_direction_id',
                '{{%catalog_listing_banners}}',
                'direction_id',
                '{{%catalog_directions}}',
                'id',
                'CASCADE',
                'CASCADE'
            );
        }

        $now = date('Y-m-d H:i:s');
        $exists = (new Query())
            ->from('{{%content_pages}}')
            ->where(['slug' => 'catalog'])
            ->exists();

        if (!$exists) {
            $this->insert('{{%content_pages}}', [
                'slug' => 'catalog',
                'title' => 'Каталог',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function safeDown(): void
    {
        if ($this->db->getTableSchema('{{%catalog_listing_banners}}', true)) {
            $this->dropForeignKey('fk_catalog_listing_banners_direction_id', '{{%catalog_listing_banners}}');
            $this->dropTable('{{%catalog_listing_banners}}');
        }

        $this->delete('{{%content_pages}}', ['slug' => 'catalog']);
    }
}
