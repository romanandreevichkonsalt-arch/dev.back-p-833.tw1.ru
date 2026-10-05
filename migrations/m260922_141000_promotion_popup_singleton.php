<?php

use yii\db\Migration;

class m260922_141000_promotion_popup_singleton extends Migration
{
    private const POPUP_ID = 1;

    public function safeUp(): void
    {
        if (!$this->db->schema->getTableSchema('{{%promotion_popup}}', true)) {
            $this->createTable('{{%promotion_popup}}', [
                'id' => $this->primaryKey(),
                'image_media_id' => $this->integer()->null(),
                'headline' => $this->string(512)->notNull()->defaultValue(''),
                'body_text' => $this->text()->null(),
                'price_current' => $this->string(64)->null(),
                'price_old' => $this->string(64)->null(),
                'cta_label' => $this->string(128)->null(),
                'cta_url' => $this->string(512)->null(),
                'promo_label' => $this->string(255)->null(),
                'template_id' => $this->integer()->null(),
                'dismiss_until_end_of_day' => $this->boolean()->notNull()->defaultValue(true),
                'valid_from' => $this->date()->null(),
                'valid_to' => $this->date()->null(),
                'updated_at' => $this->dateTime()->notNull(),
            ]);
            $this->insert('{{%promotion_popup}}', [
                'id' => self::POPUP_ID,
                'headline' => '',
                'dismiss_until_end_of_day' => true,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        $this->addForeignKeyIfMissing(
            'fk_promotion_popup_image_media_id',
            '{{%promotion_popup}}',
            'image_media_id',
            '{{%media_files}}',
            'id',
            'SET NULL',
            'CASCADE',
        );
        $this->addForeignKeyIfMissing(
            'fk_promotion_popup_template_id',
            '{{%promotion_popup}}',
            'template_id',
            '{{%promo_code_templates}}',
            'id',
            'SET NULL',
            'CASCADE',
        );

        $bannerSchema = $this->db->schema->getTableSchema('{{%promotion_banners}}', true);
        if ($bannerSchema === null) {
            return;
        }

        try {
            $this->dropForeignKey('fk_promotion_banners_popup_image_media_id', '{{%promotion_banners}}');
        } catch (\Throwable) {
        }

        if ($bannerSchema->getColumn('is_active') !== null) {
            try {
                $this->dropIndex('idx_promotion_banners_active_sort', '{{%promotion_banners}}');
            } catch (\Throwable) {
            }
        }

        $dropBannerColumns = [
            'admin_title',
            'sort_order',
            'popup_enabled',
            'popup_use_banner_content',
            'popup_image_media_id',
            'popup_headline',
            'popup_body_text',
            'popup_price_current',
            'popup_price_old',
            'popup_cta_label',
            'popup_cta_url',
            'popup_dismiss_until_end_of_day',
            'is_active',
        ];
        foreach ($dropBannerColumns as $column) {
            $schema = $this->db->schema->getTableSchema('{{%promotion_banners}}', true);
            if ($schema !== null && $schema->getColumn($column) !== null) {
                $this->dropColumn('{{%promotion_banners}}', $column);
            }
        }
    }

    public function safeDown(): void
    {
        $this->dropForeignKeyIfExists('fk_promotion_popup_template_id', '{{%promotion_popup}}');
        $this->dropForeignKeyIfExists('fk_promotion_popup_image_media_id', '{{%promotion_popup}}');
        $this->dropTable('{{%promotion_popup}}');
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
        try {
            $this->addForeignKey($name, $table, $column, $refTable, $refColumn, $delete, $update);
        } catch (\Throwable) {
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
