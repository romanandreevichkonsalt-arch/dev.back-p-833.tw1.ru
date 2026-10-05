<?php

use yii\db\Migration;

/**
 * Удаление fabrics v1 и полей fabric_id / variant_group_id на товарах.
 * Целевая модель — catalog v2 (см. memory-bank/creative/creative-catalog-v2.md).
 */
class m260807_100000_catalog_fabric_v1_cleanup extends Migration
{
    public function safeUp(): void
    {
        $productSchema = $this->db->getTableSchema('{{%catalog_products}}');
        if ($productSchema !== null && $productSchema->getColumn('fabric_id') !== null) {
            $this->dropForeignKey('fk_catalog_products_fabric_id', '{{%catalog_products}}');
            $this->dropIndex('idx_catalog_products_variant_group_id', '{{%catalog_products}}');
            $this->dropIndex('idx_catalog_products_fabric_id', '{{%catalog_products}}');
            $this->dropColumn('{{%catalog_products}}', 'variant_group_id');
            $this->dropColumn('{{%catalog_products}}', 'fabric_id');
        }

        $tables = [
            '{{%catalog_fabric_attribute_links}}',
            '{{%catalog_fabric_attributes}}',
            '{{%catalog_fabric_attribute_groups}}',
            '{{%catalog_fabric_images}}',
            '{{%catalog_fabrics}}',
            '{{%catalog_fabric_collections}}',
            '{{%catalog_fabric_manufacturers}}',
            '{{%catalog_fabric_textures}}',
        ];

        foreach ($tables as $table) {
            if ($this->db->getTableSchema($table) !== null) {
                $this->dropTable($table);
            }
        }
    }

    public function safeDown(): void
    {
        echo "m260807_100000_catalog_fabric_v1_cleanup cannot be reverted.\n";
    }
}
