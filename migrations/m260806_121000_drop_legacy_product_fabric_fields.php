<?php

use yii\db\Migration;

class m260806_121000_drop_legacy_product_fabric_fields extends Migration
{
    public function safeUp(): void
    {
        if ($this->db->getTableSchema('{{%catalog_products}}')->getColumn('fabric') !== null) {
            $this->dropColumn('{{%catalog_products}}', 'fabric');
        }
        if ($this->db->getTableSchema('{{%catalog_products}}')->getColumn('swatch_count') !== null) {
            $this->dropColumn('{{%catalog_products}}', 'swatch_count');
        }
    }

    public function safeDown(): void
    {
        $this->addColumn('{{%catalog_products}}', 'fabric', $this->string(255)->null()->after('badge_id'));
        $this->addColumn('{{%catalog_products}}', 'swatch_count', $this->string(16)->null()->after('fabric'));
    }
}
