<?php

use yii\db\Migration;

class m260922_130000_promotion_banners_and_catalog_promotions extends Migration
{
    public function safeUp(): void
    {
        if (!$this->db->schema->getTableSchema('{{%promotion_banners}}', true)) {
            $this->createTable('{{%promotion_banners}}', [
                'id' => $this->primaryKey(),
                'admin_title' => $this->string(255)->notNull(),
                'is_active' => $this->boolean()->notNull()->defaultValue(true),
                'sort_order' => $this->integer()->notNull()->defaultValue(0),
                'image_media_id' => $this->integer()->null(),
                'headline' => $this->string(512)->notNull()->defaultValue(''),
                'body_text' => $this->text()->null(),
                'price_current' => $this->string(64)->null(),
                'price_old' => $this->string(64)->null(),
                'cta_label' => $this->string(128)->null(),
                'cta_url' => $this->string(512)->null(),
                'promo_label' => $this->string(255)->null(),
                'template_id' => $this->integer()->null(),
                'popup_enabled' => $this->boolean()->notNull()->defaultValue(false),
                'popup_use_banner_content' => $this->boolean()->notNull()->defaultValue(true),
                'popup_image_media_id' => $this->integer()->null(),
                'popup_headline' => $this->string(512)->null(),
                'popup_body_text' => $this->text()->null(),
                'popup_price_current' => $this->string(64)->null(),
                'popup_price_old' => $this->string(64)->null(),
                'popup_cta_label' => $this->string(128)->null(),
                'popup_cta_url' => $this->string(512)->null(),
                'popup_dismiss_until_end_of_day' => $this->boolean()->notNull()->defaultValue(true),
                'valid_from' => $this->date()->null(),
                'valid_to' => $this->date()->null(),
                'created_at' => $this->dateTime()->notNull(),
                'updated_at' => $this->dateTime()->notNull(),
            ]);
            $this->createIndex('idx_promotion_banners_active_sort', '{{%promotion_banners}}', ['is_active', 'sort_order']);
        }

        $this->addForeignKeyIfMissing(
            'fk_promotion_banners_image_media_id',
            '{{%promotion_banners}}',
            'image_media_id',
            '{{%media_files}}',
            'id',
            'SET NULL',
            'CASCADE',
        );
        $this->addForeignKeyIfMissing(
            'fk_promotion_banners_popup_image_media_id',
            '{{%promotion_banners}}',
            'popup_image_media_id',
            '{{%media_files}}',
            'id',
            'SET NULL',
            'CASCADE',
        );
        $this->addForeignKeyIfMissing(
            'fk_promotion_banners_template_id',
            '{{%promotion_banners}}',
            'template_id',
            '{{%promo_code_templates}}',
            'id',
            'SET NULL',
            'CASCADE',
        );

        if (!$this->db->schema->getTableSchema('{{%catalog_promotions}}', true)) {
            $this->createTable('{{%catalog_promotions}}', [
                'id' => $this->primaryKey(),
                'title' => $this->string(255)->notNull(),
                'is_active' => $this->boolean()->notNull()->defaultValue(true),
                'discount_type' => $this->string(16)->notNull(),
                'discount_value' => $this->decimal(12, 2)->notNull(),
                'starts_at' => $this->dateTime()->notNull(),
                'ends_at' => $this->dateTime()->notNull(),
                'scope_type' => $this->string(16)->notNull(),
                'catalog_model_id' => $this->integer()->notNull(),
                'catalog_product_id' => $this->integer()->null(),
                'priority' => $this->integer()->notNull()->defaultValue(0),
                'created_at' => $this->dateTime()->notNull(),
                'updated_at' => $this->dateTime()->notNull(),
            ]);
            $this->createIndex(
                'idx_catalog_promotions_scope',
                '{{%catalog_promotions}}',
                ['is_active', 'catalog_model_id', 'catalog_product_id', 'starts_at', 'ends_at'],
            );
        }

        $this->addForeignKeyIfMissing(
            'fk_catalog_promotions_catalog_model_id',
            '{{%catalog_promotions}}',
            'catalog_model_id',
            '{{%catalog_models}}',
            'id',
            'CASCADE',
            'CASCADE',
        );
        $this->addForeignKeyIfMissing(
            'fk_catalog_promotions_catalog_product_id',
            '{{%catalog_promotions}}',
            'catalog_product_id',
            '{{%catalog_products}}',
            'id',
            'CASCADE',
            'CASCADE',
        );
    }

    public function safeDown(): void
    {
        $this->dropForeignKeyIfExists('fk_catalog_promotions_catalog_product_id', '{{%catalog_promotions}}');
        $this->dropForeignKeyIfExists('fk_catalog_promotions_catalog_model_id', '{{%catalog_promotions}}');
        $this->dropTable('{{%catalog_promotions}}');

        $this->dropForeignKeyIfExists('fk_promotion_banners_template_id', '{{%promotion_banners}}');
        $this->dropForeignKeyIfExists('fk_promotion_banners_popup_image_media_id', '{{%promotion_banners}}');
        $this->dropForeignKeyIfExists('fk_promotion_banners_image_media_id', '{{%promotion_banners}}');
        $this->dropTable('{{%promotion_banners}}');
    }

    private function addForeignKeyIfMissing(
        string $name,
        string $table,
        string $column,
        string $refTable,
        string $refColumn,
        string $delete,
        string $update,
    ): void {
        $schema = $this->db->schema->getTableSchema($table, true);
        if ($schema === null || $schema->getColumn($column) === null) {
            return;
        }

        try {
            $this->addForeignKey($name, $table, $column, $refTable, $refColumn, $delete, $update);
        } catch (\Throwable) {
            // FK already exists
        }
    }

    private function dropForeignKeyIfExists(string $name, string $table): void
    {
        if ($this->db->schema->getTableSchema($table, true) === null) {
            return;
        }

        try {
            $this->dropForeignKey($name, $table);
        } catch (\Throwable) {
        }
    }
}
