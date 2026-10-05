<?php

use yii\db\Migration;

class m260806_120000_catalog_fabric_tables extends Migration
{
    public function safeUp(): void
    {
        $now = date('Y-m-d H:i:s');

        $this->createTable('{{%catalog_fabric_textures}}', [
            'id' => $this->primaryKey(),
            'slug' => $this->string(64)->notNull()->unique(),
            'label' => $this->string(255)->notNull(),
            'sort_order' => $this->integer()->notNull()->defaultValue(0),
            'is_active' => $this->boolean()->notNull()->defaultValue(true),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);

        $this->createTable('{{%catalog_fabric_manufacturers}}', [
            'id' => $this->primaryKey(),
            'slug' => $this->string(64)->notNull()->unique(),
            'label' => $this->string(255)->notNull(),
            'sort_order' => $this->integer()->notNull()->defaultValue(0),
            'is_active' => $this->boolean()->notNull()->defaultValue(true),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);

        $this->createTable('{{%catalog_fabric_collections}}', [
            'id' => $this->primaryKey(),
            'slug' => $this->string(64)->notNull()->unique(),
            'name' => $this->string(255)->notNull(),
            'sort_order' => $this->integer()->notNull()->defaultValue(0),
            'is_active' => $this->boolean()->notNull()->defaultValue(true),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);

        $this->createTable('{{%catalog_fabrics}}', [
            'id' => $this->primaryKey(),
            'slug' => $this->string(64)->notNull()->unique(),
            'name' => $this->string(255)->notNull(),
            'color_label' => $this->string(128)->notNull(),
            'texture_id' => $this->integer()->notNull(),
            'fabric_collection_id' => $this->integer()->notNull(),
            'manufacturer_id' => $this->integer()->null(),
            'manufacturer_custom' => $this->string(255)->null(),
            'sort_order' => $this->integer()->notNull()->defaultValue(0),
            'is_active' => $this->boolean()->notNull()->defaultValue(true),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex('idx_catalog_fabrics_texture_id', '{{%catalog_fabrics}}', 'texture_id');
        $this->createIndex('idx_catalog_fabrics_fabric_collection_id', '{{%catalog_fabrics}}', 'fabric_collection_id');
        $this->createIndex('idx_catalog_fabrics_manufacturer_id', '{{%catalog_fabrics}}', 'manufacturer_id');
        $this->addForeignKey(
            'fk_catalog_fabrics_texture_id',
            '{{%catalog_fabrics}}',
            'texture_id',
            '{{%catalog_fabric_textures}}',
            'id',
            'RESTRICT',
            'CASCADE'
        );
        $this->addForeignKey(
            'fk_catalog_fabrics_fabric_collection_id',
            '{{%catalog_fabrics}}',
            'fabric_collection_id',
            '{{%catalog_fabric_collections}}',
            'id',
            'RESTRICT',
            'CASCADE'
        );
        $this->addForeignKey(
            'fk_catalog_fabrics_manufacturer_id',
            '{{%catalog_fabrics}}',
            'manufacturer_id',
            '{{%catalog_fabric_manufacturers}}',
            'id',
            'SET NULL',
            'CASCADE'
        );

        $this->createTable('{{%catalog_fabric_images}}', [
            'id' => $this->primaryKey(),
            'fabric_id' => $this->integer()->notNull(),
            'media_file_id' => $this->integer()->notNull(),
            'sort_order' => $this->integer()->notNull()->defaultValue(0),
            'created_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex('ux_catalog_fabric_images_fabric_media', '{{%catalog_fabric_images}}', ['fabric_id', 'media_file_id'], true);
        $this->createIndex('idx_catalog_fabric_images_fabric_id', '{{%catalog_fabric_images}}', 'fabric_id');
        $this->addForeignKey(
            'fk_catalog_fabric_images_fabric_id',
            '{{%catalog_fabric_images}}',
            'fabric_id',
            '{{%catalog_fabrics}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
        $this->addForeignKey(
            'fk_catalog_fabric_images_media_file_id',
            '{{%catalog_fabric_images}}',
            'media_file_id',
            '{{%media_files}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->addColumn('{{%catalog_products}}', 'fabric_id', $this->integer()->null()->after('collection_id'));
        $this->addColumn('{{%catalog_products}}', 'variant_group_id', $this->integer()->null()->after('fabric_id'));
        $this->createIndex('idx_catalog_products_fabric_id', '{{%catalog_products}}', 'fabric_id');
        $this->createIndex('idx_catalog_products_variant_group_id', '{{%catalog_products}}', 'variant_group_id');
        $this->addForeignKey(
            'fk_catalog_products_fabric_id',
            '{{%catalog_products}}',
            'fabric_id',
            '{{%catalog_fabrics}}',
            'id',
            'SET NULL',
            'CASCADE'
        );

        foreach (
            [
                ['Велюр', 'velour'],
                ['Букле', 'boucle'],
                ['Вельвет', 'velvet'],
                ['Рогожка', 'rogozhka'],
                ['Шенилл', 'chenille'],
            ] as $index => [$label, $slug]
        ) {
            $this->insert('{{%catalog_fabric_textures}}', [
                'slug' => $slug,
                'label' => $label,
                'sort_order' => $index,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function safeDown(): void
    {
        $this->dropForeignKey('fk_catalog_products_fabric_id', '{{%catalog_products}}');
        $this->dropIndex('idx_catalog_products_variant_group_id', '{{%catalog_products}}');
        $this->dropIndex('idx_catalog_products_fabric_id', '{{%catalog_products}}');
        $this->dropColumn('{{%catalog_products}}', 'variant_group_id');
        $this->dropColumn('{{%catalog_products}}', 'fabric_id');

        $this->dropForeignKey('fk_catalog_fabric_images_media_file_id', '{{%catalog_fabric_images}}');
        $this->dropForeignKey('fk_catalog_fabric_images_fabric_id', '{{%catalog_fabric_images}}');
        $this->dropTable('{{%catalog_fabric_images}}');

        $this->dropForeignKey('fk_catalog_fabrics_manufacturer_id', '{{%catalog_fabrics}}');
        $this->dropForeignKey('fk_catalog_fabrics_fabric_collection_id', '{{%catalog_fabrics}}');
        $this->dropForeignKey('fk_catalog_fabrics_texture_id', '{{%catalog_fabrics}}');
        $this->dropTable('{{%catalog_fabrics}}');

        $this->dropTable('{{%catalog_fabric_collections}}');
        $this->dropTable('{{%catalog_fabric_manufacturers}}');
        $this->dropTable('{{%catalog_fabric_textures}}');
    }
}
