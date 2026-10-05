<?php

use yii\db\Migration;

class m260807_180000_catalog_model_dimension_images extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%catalog_model_dimension_images}}', [
            'id' => $this->primaryKey(),
            'model_id' => $this->integer()->notNull(),
            'media_file_id' => $this->integer()->notNull(),
            'sort_order' => $this->integer()->notNull()->defaultValue(0),
            'created_at' => $this->dateTime()->notNull(),
        ]);

        $this->createIndex(
            'ux_catalog_model_dimension_images_model_media',
            '{{%catalog_model_dimension_images}}',
            ['model_id', 'media_file_id'],
            true
        );
        $this->addForeignKey(
            'fk_catalog_model_dimension_images_model_id',
            '{{%catalog_model_dimension_images}}',
            'model_id',
            '{{%catalog_models}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
        $this->addForeignKey(
            'fk_catalog_model_dimension_images_media_file_id',
            '{{%catalog_model_dimension_images}}',
            'media_file_id',
            '{{%media_files}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
    }

    public function safeDown(): void
    {
        $this->dropForeignKey('fk_catalog_model_dimension_images_media_file_id', '{{%catalog_model_dimension_images}}');
        $this->dropForeignKey('fk_catalog_model_dimension_images_model_id', '{{%catalog_model_dimension_images}}');
        $this->dropTable('{{%catalog_model_dimension_images}}');
    }
}
