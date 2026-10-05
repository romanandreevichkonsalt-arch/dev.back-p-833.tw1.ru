<?php

use yii\db\Migration;

class m260805_160000_create_catalog_tables extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%catalog_groups}}', [
            'id' => $this->primaryKey(),
            'slug' => $this->string(64)->notNull()->unique(),
            'label' => $this->string(255)->notNull(),
            'sort_order' => $this->integer()->notNull()->defaultValue(0),
            'is_active' => $this->boolean()->notNull()->defaultValue(true),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);

        $this->createTable('{{%catalog_subcategories}}', [
            'id' => $this->primaryKey(),
            'group_id' => $this->integer()->notNull(),
            'slug' => $this->string(64)->notNull(),
            'label' => $this->string(255)->notNull(),
            'sort_order' => $this->integer()->notNull()->defaultValue(0),
            'is_active' => $this->boolean()->notNull()->defaultValue(true),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex('ux_catalog_subcategories_group_slug', '{{%catalog_subcategories}}', ['group_id', 'slug'], true);
        $this->addForeignKey('fk_catalog_subcategories_group_id', '{{%catalog_subcategories}}', 'group_id', '{{%catalog_groups}}', 'id', 'CASCADE', 'CASCADE');

        $this->createTable('{{%catalog_collections}}', [
            'id' => $this->primaryKey(),
            'slug' => $this->string(64)->notNull()->unique(),
            'label' => $this->string(255)->notNull(),
            'title' => $this->string(255)->notNull(),
            'title_uppercase' => $this->boolean()->notNull()->defaultValue(false),
            'description' => $this->text()->null(),
            'href' => $this->string(512)->notNull(),
            'cta_label' => $this->string(255)->null(),
            'image_id' => $this->integer()->null(),
            'image_position' => $this->string(64)->null(),
            'sort_order' => $this->integer()->notNull()->defaultValue(0),
            'is_active' => $this->boolean()->notNull()->defaultValue(true),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);
        $this->addForeignKey('fk_catalog_collections_image_id', '{{%catalog_collections}}', 'image_id', '{{%media_files}}', 'id', 'SET NULL', 'CASCADE');

        $this->createTable('{{%catalog_products}}', [
            'id' => $this->primaryKey(),
            'slug' => $this->string(64)->notNull()->unique(),
            'title' => $this->string(255)->notNull(),
            'subcategory_id' => $this->integer()->null(),
            'collection_id' => $this->integer()->null(),
            'type_label' => $this->string(128)->null(),
            'collection_label' => $this->string(255)->null(),
            'price_display' => $this->string(64)->null(),
            'href' => $this->string(512)->notNull(),
            'image_id' => $this->integer()->null(),
            'image_position' => $this->string(64)->null(),
            'badge_text' => $this->string(64)->null(),
            'badge_variant' => $this->string(32)->null(),
            'fabric' => $this->string(255)->null(),
            'swatch_count' => $this->string(16)->null(),
            'layout' => $this->string(32)->null(),
            'sort_order' => $this->integer()->notNull()->defaultValue(0),
            'is_active' => $this->boolean()->notNull()->defaultValue(true),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex('idx_catalog_products_subcategory_id', '{{%catalog_products}}', 'subcategory_id');
        $this->addForeignKey('fk_catalog_products_subcategory_id', '{{%catalog_products}}', 'subcategory_id', '{{%catalog_subcategories}}', 'id', 'SET NULL', 'CASCADE');
        $this->addForeignKey('fk_catalog_products_collection_id', '{{%catalog_products}}', 'collection_id', '{{%catalog_collections}}', 'id', 'SET NULL', 'CASCADE');
        $this->addForeignKey('fk_catalog_products_image_id', '{{%catalog_products}}', 'image_id', '{{%media_files}}', 'id', 'SET NULL', 'CASCADE');
    }

    public function safeDown(): void
    {
        $this->dropForeignKey('fk_catalog_products_image_id', '{{%catalog_products}}');
        $this->dropForeignKey('fk_catalog_products_collection_id', '{{%catalog_products}}');
        $this->dropForeignKey('fk_catalog_products_subcategory_id', '{{%catalog_products}}');
        $this->dropTable('{{%catalog_products}}');

        $this->dropForeignKey('fk_catalog_collections_image_id', '{{%catalog_collections}}');
        $this->dropTable('{{%catalog_collections}}');

        $this->dropForeignKey('fk_catalog_subcategories_group_id', '{{%catalog_subcategories}}');
        $this->dropTable('{{%catalog_subcategories}}');

        $this->dropTable('{{%catalog_groups}}');
    }
}
