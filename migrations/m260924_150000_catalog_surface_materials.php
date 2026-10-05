<?php

use yii\db\Migration;

class m260924_150000_catalog_surface_materials extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%catalog_surface_materials}}', [
            'id' => $this->primaryKey(),
            'registry_number' => $this->integer()->null(),
            'material_type' => $this->string(32)->notNull(),
            'name' => $this->string(255)->notNull(),
            'slug' => $this->string(64)->notNull(),
            'applied_models_text' => $this->text()->null(),
            'description' => $this->text()->null(),
            'source_photo_url' => $this->string(512)->null(),
            'photo_media_id' => $this->integer()->null(),
            'source_texture_url' => $this->string(512)->null(),
            'texture_media_id' => $this->integer()->null(),
            'import_source' => $this->string(64)->null(),
            'import_row_hash' => $this->string(64)->null(),
            'sort_order' => $this->integer()->notNull()->defaultValue(0),
            'is_active' => $this->boolean()->notNull()->defaultValue(true),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);

        $this->createIndex(
            'uidx_catalog_surface_materials_type_slug',
            '{{%catalog_surface_materials}}',
            ['material_type', 'slug'],
            true
        );
        $this->createIndex('idx_catalog_surface_materials_type', '{{%catalog_surface_materials}}', 'material_type');

        $this->addForeignKey(
            'fk_catalog_surface_materials_photo_media_id',
            '{{%catalog_surface_materials}}',
            'photo_media_id',
            '{{%media_files}}',
            'id',
            'SET NULL',
            'CASCADE'
        );
        $this->addForeignKey(
            'fk_catalog_surface_materials_texture_media_id',
            '{{%catalog_surface_materials}}',
            'texture_media_id',
            '{{%media_files}}',
            'id',
            'SET NULL',
            'CASCADE'
        );

        $this->createTable('{{%catalog_surface_material_collections}}', [
            'surface_material_id' => $this->integer()->notNull(),
            'collection_id' => $this->integer()->notNull(),
            'created_at' => $this->dateTime()->notNull(),
        ]);
        $this->addPrimaryKey(
            'pk_catalog_surface_material_collections',
            '{{%catalog_surface_material_collections}}',
            ['surface_material_id', 'collection_id']
        );
        $this->createIndex(
            'idx_catalog_surface_material_collections_collection',
            '{{%catalog_surface_material_collections}}',
            'collection_id'
        );
        $this->addForeignKey(
            'fk_csmc_surface_material_id',
            '{{%catalog_surface_material_collections}}',
            'surface_material_id',
            '{{%catalog_surface_materials}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
        $this->addForeignKey(
            'fk_csmc_collection_id',
            '{{%catalog_surface_material_collections}}',
            'collection_id',
            '{{%catalog_collections}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $exists = (new \yii\db\Query())
            ->from('{{%media_folders}}')
            ->where(['slug' => 'surface-materials'])
            ->exists($this->db);
        if (!$exists) {
            $this->insert('{{%media_folders}}', [
                'slug' => 'surface-materials',
                'label' => 'Материалы',
                'sort_order' => 52,
            ]);
        }
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%catalog_surface_material_collections}}');
        $this->dropTable('{{%catalog_surface_materials}}');
        $this->delete('{{%media_folders}}', ['slug' => 'surface-materials']);
    }
}
