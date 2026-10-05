<?php

use yii\db\Migration;

class m260807_120000_catalog_v2 extends Migration
{
    public function safeUp(): void
    {
        $now = date('Y-m-d H:i:s');

        $this->createTable('{{%catalog_fabric_collections}}', [
            'id' => $this->primaryKey(),
            'slug' => $this->string(64)->notNull()->unique(),
            'name' => $this->string(255)->notNull(),
            'description' => $this->text()->null(),
            'sort_order' => $this->integer()->notNull()->defaultValue(0),
            'is_active' => $this->boolean()->notNull()->defaultValue(true),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);

        $this->createTable('{{%catalog_fabric_colors}}', [
            'id' => $this->primaryKey(),
            'fabric_collection_id' => $this->integer()->notNull(),
            'slug' => $this->string(64)->notNull(),
            'label' => $this->string(255)->notNull(),
            'category' => $this->tinyInteger()->notNull()->comment('Ценовая категория ткани 1–8'),
            'sort_order' => $this->integer()->notNull()->defaultValue(0),
            'is_active' => $this->boolean()->notNull()->defaultValue(true),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex('ux_catalog_fabric_colors_collection_slug', '{{%catalog_fabric_colors}}', ['fabric_collection_id', 'slug'], true);
        $this->createIndex('idx_catalog_fabric_colors_fabric_collection_id', '{{%catalog_fabric_colors}}', 'fabric_collection_id');
        $this->addForeignKey(
            'fk_catalog_fabric_colors_fabric_collection_id',
            '{{%catalog_fabric_colors}}',
            'fabric_collection_id',
            '{{%catalog_fabric_collections}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->createTable('{{%catalog_fabric_color_images}}', [
            'id' => $this->primaryKey(),
            'fabric_color_id' => $this->integer()->notNull(),
            'media_file_id' => $this->integer()->notNull(),
            'sort_order' => $this->integer()->notNull()->defaultValue(0),
            'created_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex('ux_catalog_fabric_color_images_color_media', '{{%catalog_fabric_color_images}}', ['fabric_color_id', 'media_file_id'], true);
        $this->addForeignKey(
            'fk_catalog_fabric_color_images_fabric_color_id',
            '{{%catalog_fabric_color_images}}',
            'fabric_color_id',
            '{{%catalog_fabric_colors}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
        $this->addForeignKey(
            'fk_catalog_fabric_color_images_media_file_id',
            '{{%catalog_fabric_color_images}}',
            'media_file_id',
            '{{%media_files}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->createTable('{{%catalog_models}}', [
            'id' => $this->primaryKey(),
            'collection_id' => $this->integer()->notNull(),
            'subcategory_id' => $this->integer()->null(),
            'type_id' => $this->integer()->null(),
            'slug' => $this->string(64)->notNull()->unique(),
            'title' => $this->string(255)->notNull(),
            'subtitle' => $this->string(255)->null(),
            'description' => $this->text()->null(),
            'layout_id' => $this->integer()->null(),
            'badge_id' => $this->integer()->null(),
            'video_id' => $this->integer()->null(),
            'overall_size' => $this->string(64)->null(),
            'seat_depth' => $this->string(64)->null(),
            'seat_height' => $this->string(64)->null(),
            'armrest_width' => $this->string(64)->null(),
            'clearance' => $this->string(64)->null(),
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
        $this->createIndex('idx_catalog_models_collection_id', '{{%catalog_models}}', 'collection_id');
        $this->addForeignKey('fk_catalog_models_collection_id', '{{%catalog_models}}', 'collection_id', '{{%catalog_collections}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_catalog_models_subcategory_id', '{{%catalog_models}}', 'subcategory_id', '{{%catalog_subcategories}}', 'id', 'SET NULL', 'CASCADE');
        $this->addForeignKey('fk_catalog_models_type_id', '{{%catalog_models}}', 'type_id', '{{%catalog_types}}', 'id', 'SET NULL', 'CASCADE');
        $this->addForeignKey('fk_catalog_models_layout_id', '{{%catalog_models}}', 'layout_id', '{{%catalog_layouts}}', 'id', 'SET NULL', 'CASCADE');
        $this->addForeignKey('fk_catalog_models_badge_id', '{{%catalog_models}}', 'badge_id', '{{%catalog_badges}}', 'id', 'SET NULL', 'CASCADE');
        $this->addForeignKey('fk_catalog_models_video_id', '{{%catalog_models}}', 'video_id', '{{%media_files}}', 'id', 'SET NULL', 'CASCADE');

        $this->createTable('{{%catalog_model_prices}}', [
            'id' => $this->primaryKey(),
            'model_id' => $this->integer()->notNull(),
            'category' => $this->tinyInteger()->notNull(),
            'price_display' => $this->string(64)->null(),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex('ux_catalog_model_prices_model_category', '{{%catalog_model_prices}}', ['model_id', 'category'], true);
        $this->addForeignKey(
            'fk_catalog_model_prices_model_id',
            '{{%catalog_model_prices}}',
            'model_id',
            '{{%catalog_models}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->createTable('{{%catalog_model_fabric_collections}}', [
            'model_id' => $this->integer()->notNull(),
            'fabric_collection_id' => $this->integer()->notNull(),
            'created_at' => $this->dateTime()->notNull(),
        ]);
        $this->addPrimaryKey('pk_catalog_model_fabric_collections', '{{%catalog_model_fabric_collections}}', ['model_id', 'fabric_collection_id']);
        $this->addForeignKey(
            'fk_catalog_model_fabric_collections_model_id',
            '{{%catalog_model_fabric_collections}}',
            'model_id',
            '{{%catalog_models}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
        $this->addForeignKey(
            'fk_catalog_model_fabric_collections_fabric_collection_id',
            '{{%catalog_model_fabric_collections}}',
            'fabric_collection_id',
            '{{%catalog_fabric_collections}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->createTable('{{%catalog_model_images}}', [
            'id' => $this->primaryKey(),
            'model_id' => $this->integer()->notNull(),
            'media_file_id' => $this->integer()->notNull(),
            'sort_order' => $this->integer()->notNull()->defaultValue(0),
            'created_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex('ux_catalog_model_images_model_media', '{{%catalog_model_images}}', ['model_id', 'media_file_id'], true);
        $this->addForeignKey('fk_catalog_model_images_model_id', '{{%catalog_model_images}}', 'model_id', '{{%catalog_models}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk_catalog_model_images_media_file_id', '{{%catalog_model_images}}', 'media_file_id', '{{%media_files}}', 'id', 'CASCADE', 'CASCADE');

        $this->addColumn('{{%catalog_products}}', 'model_id', $this->integer()->null()->after('collection_id'));
        $this->addColumn('{{%catalog_products}}', 'fabric_color_id', $this->integer()->null()->after('model_id'));
        $this->createIndex('idx_catalog_products_model_id', '{{%catalog_products}}', 'model_id');
        $this->createIndex('idx_catalog_products_fabric_color_id', '{{%catalog_products}}', 'fabric_color_id');
        $this->createIndex('ux_catalog_products_model_fabric_color', '{{%catalog_products}}', ['model_id', 'fabric_color_id'], true);
        $this->addForeignKey('fk_catalog_products_model_id', '{{%catalog_products}}', 'model_id', '{{%catalog_models}}', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey(
            'fk_catalog_products_fabric_color_id',
            '{{%catalog_products}}',
            'fabric_color_id',
            '{{%catalog_fabric_colors}}',
            'id',
            'SET NULL',
            'CASCADE'
        );
    }

    public function safeDown(): bool
    {
        $this->dropForeignKey('fk_catalog_products_fabric_color_id', '{{%catalog_products}}');
        $this->dropForeignKey('fk_catalog_products_model_id', '{{%catalog_products}}');
        $this->dropIndex('ux_catalog_products_model_fabric_color', '{{%catalog_products}}');
        $this->dropIndex('idx_catalog_products_fabric_color_id', '{{%catalog_products}}');
        $this->dropIndex('idx_catalog_products_model_id', '{{%catalog_products}}');
        $this->dropColumn('{{%catalog_products}}', 'fabric_color_id');
        $this->dropColumn('{{%catalog_products}}', 'model_id');

        $this->dropTable('{{%catalog_model_images}}');
        $this->dropTable('{{%catalog_model_fabric_collections}}');
        $this->dropTable('{{%catalog_model_prices}}');
        $this->dropTable('{{%catalog_models}}');
        $this->dropTable('{{%catalog_fabric_color_images}}');
        $this->dropTable('{{%catalog_fabric_colors}}');
        $this->dropTable('{{%catalog_fabric_collections}}');

        return true;
    }
}
