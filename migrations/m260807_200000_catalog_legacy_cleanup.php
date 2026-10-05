<?php

use yii\db\Migration;

class m260807_200000_catalog_legacy_cleanup extends Migration
{
    public function safeUp(): void
    {
        if ($this->db->schema->getTableSchema('{{%catalog_products}}') !== null) {
            if ($this->db->schema->getTableSchema('{{%catalog_products}}')->getColumn('material_template_id') !== null) {
                $this->dropForeignKey('fk_catalog_products_material_template_id', '{{%catalog_products}}');
                $this->dropColumn('{{%catalog_products}}', 'material_template_id');
            }

            if ($this->db->schema->getTableSchema('{{%catalog_products}}')->getColumn('dimension_template_id') !== null) {
                $this->dropForeignKey('fk_catalog_products_dimension_template_id', '{{%catalog_products}}');
                $this->dropColumn('{{%catalog_products}}', 'dimension_template_id');
            }
        }

        if ($this->db->schema->getTableSchema('{{%catalog_product_images}}') !== null) {
            $this->dropForeignKey('fk_catalog_product_images_media_file_id', '{{%catalog_product_images}}');
            $this->dropForeignKey('fk_catalog_product_images_product_id', '{{%catalog_product_images}}');
            $this->dropTable('{{%catalog_product_images}}');
        }

        if ($this->db->schema->getTableSchema('{{%catalog_dimension_template_images}}') !== null) {
            $this->dropForeignKey('fk_catalog_dimension_template_images_media_file_id', '{{%catalog_dimension_template_images}}');
            $this->dropForeignKey('fk_catalog_dimension_template_images_template_id', '{{%catalog_dimension_template_images}}');
            $this->dropTable('{{%catalog_dimension_template_images}}');
        }

        if ($this->db->schema->getTableSchema('{{%catalog_dimension_templates}}') !== null) {
            $this->dropTable('{{%catalog_dimension_templates}}');
        }

        if ($this->db->schema->getTableSchema('{{%catalog_material_templates}}') !== null) {
            $this->dropTable('{{%catalog_material_templates}}');
        }
    }

    public function safeDown(): void
    {
        $this->createTable('{{%catalog_material_templates}}', [
            'id' => $this->primaryKey(),
            'slug' => $this->string(64)->notNull()->unique(),
            'name' => $this->string(255)->notNull(),
            'frame' => $this->text()->null(),
            'foundation' => $this->text()->null(),
            'filling' => $this->text()->null(),
            'upholstery' => $this->text()->null(),
            'supports' => $this->text()->null(),
            'sort_order' => $this->integer()->notNull()->defaultValue(0),
            'is_active' => $this->boolean()->notNull()->defaultValue(true),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);

        $this->createTable('{{%catalog_dimension_templates}}', [
            'id' => $this->primaryKey(),
            'slug' => $this->string(64)->notNull()->unique(),
            'name' => $this->string(255)->notNull(),
            'overall_size' => $this->string(64)->null(),
            'seat_depth' => $this->string(64)->null(),
            'seat_height' => $this->string(64)->null(),
            'armrest_width' => $this->string(64)->null(),
            'clearance' => $this->string(64)->null(),
            'sort_order' => $this->integer()->notNull()->defaultValue(0),
            'is_active' => $this->boolean()->notNull()->defaultValue(true),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);

        $this->createTable('{{%catalog_dimension_template_images}}', [
            'id' => $this->primaryKey(),
            'template_id' => $this->integer()->notNull(),
            'media_file_id' => $this->integer()->notNull(),
            'sort_order' => $this->integer()->notNull()->defaultValue(0),
            'created_at' => $this->dateTime()->notNull(),
        ]);

        $this->createIndex(
            'ux_catalog_dimension_template_images_template_media',
            '{{%catalog_dimension_template_images}}',
            ['template_id', 'media_file_id'],
            true
        );

        $this->addForeignKey(
            'fk_catalog_dimension_template_images_template_id',
            '{{%catalog_dimension_template_images}}',
            'template_id',
            '{{%catalog_dimension_templates}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->addForeignKey(
            'fk_catalog_dimension_template_images_media_file_id',
            '{{%catalog_dimension_template_images}}',
            'media_file_id',
            '{{%media_files}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->createTable('{{%catalog_product_images}}', [
            'id' => $this->primaryKey(),
            'product_id' => $this->integer()->notNull(),
            'media_file_id' => $this->integer()->notNull(),
            'sort_order' => $this->integer()->notNull()->defaultValue(0),
            'created_at' => $this->dateTime()->notNull(),
        ]);

        $this->createIndex(
            'ux_catalog_product_images_product_media',
            '{{%catalog_product_images}}',
            ['product_id', 'media_file_id'],
            true
        );

        $this->addForeignKey(
            'fk_catalog_product_images_product_id',
            '{{%catalog_product_images}}',
            'product_id',
            '{{%catalog_products}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->addForeignKey(
            'fk_catalog_product_images_media_file_id',
            '{{%catalog_product_images}}',
            'media_file_id',
            '{{%media_files}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->addColumn('{{%catalog_products}}', 'dimension_template_id', $this->integer()->null()->after('layout_id'));
        $this->addColumn('{{%catalog_products}}', 'material_template_id', $this->integer()->null()->after('clearance'));

        $this->addForeignKey(
            'fk_catalog_products_dimension_template_id',
            '{{%catalog_products}}',
            'dimension_template_id',
            '{{%catalog_dimension_templates}}',
            'id',
            'SET NULL',
            'CASCADE'
        );

        $this->addForeignKey(
            'fk_catalog_products_material_template_id',
            '{{%catalog_products}}',
            'material_template_id',
            '{{%catalog_material_templates}}',
            'id',
            'SET NULL',
            'CASCADE'
        );
    }
}
