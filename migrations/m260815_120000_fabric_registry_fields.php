<?php

use yii\db\Migration;

class m260815_120000_fabric_registry_fields extends Migration
{
    public function safeUp(): void
    {
        if (!$this->columnExists('{{%catalog_price_categories}}', 'price_min')) {
            $this->addColumn('{{%catalog_price_categories}}', 'price_min', $this->integer()->null()->after('label'));
        }
        if (!$this->columnExists('{{%catalog_price_categories}}', 'price_max')) {
            $this->addColumn('{{%catalog_price_categories}}', 'price_max', $this->integer()->null()->after('price_min'));
        }

        $collectionColumns = [
            'material_kind' => $this->string(32)->notNull()->defaultValue('Ткань'),
            'texture' => $this->string(64)->null(),
            'price_category_line1_id' => $this->integer()->null(),
            'care_instructions' => $this->text()->null(),
            'available_colors_note' => $this->text()->null(),
            'import_source' => $this->string(64)->null(),
            'import_row_hash' => $this->string(64)->null(),
        ];
        foreach ($collectionColumns as $name => $type) {
            if (!$this->columnExists('{{%catalog_fabric_collections}}', $name)) {
                $this->addColumn('{{%catalog_fabric_collections}}', $name, $type);
            }
        }

        if (!$this->foreignKeyExists('fk_catalog_fabric_collections_price_category_line1_id')) {
            $this->addForeignKey(
                'fk_catalog_fabric_collections_price_category_line1_id',
                '{{%catalog_fabric_collections}}',
                'price_category_line1_id',
                '{{%catalog_price_categories}}',
                'id',
                'SET NULL',
                'CASCADE'
            );
        }

        $linkColumns = [
            'design_code' => $this->string(64)->notNull()->defaultValue(''),
            'design_label' => $this->string(255)->null(),
            'composition' => $this->text()->null(),
            'martindale' => $this->integer()->null(),
            'properties' => $this->text()->null(),
            'roll_width_cm' => $this->smallInteger()->null(),
            'density_gsm' => $this->smallInteger()->null(),
            'import_comment' => $this->text()->null(),
            'swatch_media_id' => $this->integer()->null(),
            'pbr_media_id' => $this->integer()->null(),
            'cert_media_id' => $this->integer()->null(),
            'source_photo_url' => $this->string(512)->null(),
        ];
        foreach ($linkColumns as $name => $type) {
            if (!$this->columnExists('{{%catalog_fabric_collection_colors}}', $name)) {
                $this->addColumn('{{%catalog_fabric_collection_colors}}', $name, $type);
            }
        }

        if ($this->columnExists('{{%catalog_fabric_collection_colors}}', 'swatch_media_id')) {
            $this->execute(
                "UPDATE {{%catalog_fabric_collection_colors}} l
                 INNER JOIN {{%catalog_colors}} c ON c.id = l.color_id
                 SET l.swatch_media_id = c.swatch_media_id
                 WHERE l.swatch_media_id IS NULL AND c.swatch_media_id IS NOT NULL"
            );
        }

        if ($this->columnExists('{{%catalog_fabric_collection_colors}}', 'design_code')) {
            $this->execute(
                "UPDATE {{%catalog_fabric_collection_colors}}
                 SET design_code = CONCAT('legacy-', id)
                 WHERE design_code = '' OR design_code IS NULL"
            );
        }

        if ($this->columnExists('{{%catalog_fabric_collection_colors}}', 'color_id')) {
            $this->alterColumn('{{%catalog_fabric_collection_colors}}', 'color_id', $this->integer()->null());
        }

        if ($this->foreignKeyExists('fk_catalog_fcc_color_id')) {
            $this->dropForeignKey('fk_catalog_fcc_color_id', '{{%catalog_fabric_collection_colors}}');
        }
        if ($this->foreignKeyExists('fk_catalog_fcc_fabric_collection_id')) {
            $this->dropForeignKey('fk_catalog_fcc_fabric_collection_id', '{{%catalog_fabric_collection_colors}}');
        }
        if ($this->indexExists('ux_catalog_fabric_collection_colors_pair')) {
            $this->dropIndex('ux_catalog_fabric_collection_colors_pair', '{{%catalog_fabric_collection_colors}}');
        }

        if (!$this->indexExists('ux_catalog_fabric_collection_colors_design')) {
            $this->createIndex(
                'ux_catalog_fabric_collection_colors_design',
                '{{%catalog_fabric_collection_colors}}',
                ['fabric_collection_id', 'design_code'],
                true
            );
        }

        if (!$this->foreignKeyExists('fk_catalog_fcc_fabric_collection_id')) {
            $this->addForeignKey(
                'fk_catalog_fcc_fabric_collection_id',
                '{{%catalog_fabric_collection_colors}}',
                'fabric_collection_id',
                '{{%catalog_fabric_collections}}',
                'id',
                'CASCADE',
                'CASCADE'
            );
        }

        if (!$this->foreignKeyExists('fk_catalog_fcc_color_id')) {
            $this->addForeignKey(
                'fk_catalog_fcc_color_id',
                '{{%catalog_fabric_collection_colors}}',
                'color_id',
                '{{%catalog_colors}}',
                'id',
                'SET NULL',
                'CASCADE'
            );
        }

        foreach ([
            'swatch_media_id' => 'fk_catalog_fabric_collection_colors_swatch_media_id',
            'pbr_media_id' => 'fk_catalog_fabric_collection_colors_pbr_media_id',
            'cert_media_id' => 'fk_catalog_fabric_collection_colors_cert_media_id',
        ] as $column => $fkName) {
            if (!$this->foreignKeyExists($fkName)) {
                $this->addForeignKey(
                    $fkName,
                    '{{%catalog_fabric_collection_colors}}',
                    $column,
                    '{{%media_files}}',
                    'id',
                    'SET NULL',
                    'CASCADE'
                );
            }
        }

        if (!$this->tableExists('{{%catalog_import_runs}}')) {
            $this->createTable('{{%catalog_import_runs}}', [
                'id' => $this->primaryKey(),
                'user_id' => $this->integer()->null(),
                'filename' => $this->string(255)->notNull(),
                'sheet' => $this->string(64)->notNull()->defaultValue(''),
                'status' => $this->string(32)->notNull()->defaultValue('completed'),
                'stats_json' => $this->text()->null(),
                'created_at' => $this->dateTime()->notNull(),
            ]);
        }

        if (!$this->tableExists('{{%catalog_import_media_queue}}')) {
            $this->createTable('{{%catalog_import_media_queue}}', [
                'id' => $this->primaryKey(),
                'import_run_id' => $this->integer()->notNull(),
                'row_number' => $this->integer()->notNull(),
                'source_url' => $this->string(512)->notNull(),
                'url_type' => $this->string(32)->notNull()->defaultValue('unknown'),
                'status' => $this->string(32)->notNull()->defaultValue('pending'),
                'resolved_filename' => $this->string(255)->null(),
                'media_file_id' => $this->integer()->null(),
                'error_message' => $this->text()->null(),
                'entity_type' => $this->string(64)->null(),
                'entity_id' => $this->integer()->null(),
                'created_at' => $this->dateTime()->notNull(),
            ]);
            $this->createIndex('idx_catalog_import_media_queue_run', '{{%catalog_import_media_queue}}', 'import_run_id');
            $this->addForeignKey(
                'fk_catalog_import_media_queue_run_id',
                '{{%catalog_import_media_queue}}',
                'import_run_id',
                '{{%catalog_import_runs}}',
                'id',
                'CASCADE',
                'CASCADE'
            );
            $this->addForeignKey(
                'fk_catalog_import_media_queue_media_file_id',
                '{{%catalog_import_media_queue}}',
                'media_file_id',
                '{{%media_files}}',
                'id',
                'SET NULL',
                'CASCADE'
            );
        }
    }

    public function safeDown(): void
    {
        if ($this->tableExists('{{%catalog_import_media_queue}}')) {
            if ($this->foreignKeyExists('fk_catalog_import_media_queue_media_file_id')) {
                $this->dropForeignKey('fk_catalog_import_media_queue_media_file_id', '{{%catalog_import_media_queue}}');
            }
            if ($this->foreignKeyExists('fk_catalog_import_media_queue_run_id')) {
                $this->dropForeignKey('fk_catalog_import_media_queue_run_id', '{{%catalog_import_media_queue}}');
            }
            $this->dropTable('{{%catalog_import_media_queue}}');
        }
        if ($this->tableExists('{{%catalog_import_runs}}')) {
            $this->dropTable('{{%catalog_import_runs}}');
        }

        foreach ([
            'fk_catalog_fabric_collection_colors_cert_media_id',
            'fk_catalog_fabric_collection_colors_pbr_media_id',
            'fk_catalog_fabric_collection_colors_swatch_media_id',
        ] as $fkName) {
            if ($this->foreignKeyExists($fkName)) {
                $this->dropForeignKey($fkName, '{{%catalog_fabric_collection_colors}}');
            }
        }

        if ($this->indexExists('ux_catalog_fabric_collection_colors_design')) {
            $this->dropIndex('ux_catalog_fabric_collection_colors_design', '{{%catalog_fabric_collection_colors}}');
        }

        if ($this->foreignKeyExists('fk_catalog_fcc_color_id')) {
            $this->dropForeignKey('fk_catalog_fcc_color_id', '{{%catalog_fabric_collection_colors}}');
        }

        if ($this->foreignKeyExists('fk_catalog_fcc_fabric_collection_id')) {
            $this->dropForeignKey('fk_catalog_fcc_fabric_collection_id', '{{%catalog_fabric_collection_colors}}');
        }

        if ($this->columnExists('{{%catalog_fabric_collection_colors}}', 'color_id')) {
            $this->alterColumn('{{%catalog_fabric_collection_colors}}', 'color_id', $this->integer()->notNull());
        }

        if (!$this->indexExists('ux_catalog_fabric_collection_colors_pair')) {
            $this->createIndex(
                'ux_catalog_fabric_collection_colors_pair',
                '{{%catalog_fabric_collection_colors}}',
                ['fabric_collection_id', 'color_id'],
                true
            );
        }

        if (!$this->foreignKeyExists('fk_catalog_fcc_color_id')) {
            $this->addForeignKey(
                'fk_catalog_fcc_color_id',
                '{{%catalog_fabric_collection_colors}}',
                'color_id',
                '{{%catalog_colors}}',
                'id',
                'CASCADE',
                'CASCADE'
            );
        }

        if (!$this->foreignKeyExists('fk_catalog_fcc_fabric_collection_id')) {
            $this->addForeignKey(
                'fk_catalog_fcc_fabric_collection_id',
                '{{%catalog_fabric_collection_colors}}',
                'fabric_collection_id',
                '{{%catalog_fabric_collections}}',
                'id',
                'CASCADE',
                'CASCADE'
            );
        }

        foreach (array_reverse([
            'source_photo_url', 'cert_media_id', 'pbr_media_id', 'swatch_media_id', 'import_comment',
            'density_gsm', 'roll_width_cm', 'properties', 'martindale', 'composition', 'design_label', 'design_code',
        ]) as $column) {
            if ($this->columnExists('{{%catalog_fabric_collection_colors}}', $column)) {
                $this->dropColumn('{{%catalog_fabric_collection_colors}}', $column);
            }
        }

        if ($this->foreignKeyExists('fk_catalog_fabric_collections_price_category_line1_id')) {
            $this->dropForeignKey('fk_catalog_fabric_collections_price_category_line1_id', '{{%catalog_fabric_collections}}');
        }

        foreach (array_reverse([
            'import_row_hash', 'import_source', 'available_colors_note', 'care_instructions',
            'price_category_line1_id', 'texture', 'material_kind',
        ]) as $column) {
            if ($this->columnExists('{{%catalog_fabric_collections}}', $column)) {
                $this->dropColumn('{{%catalog_fabric_collections}}', $column);
            }
        }

        foreach (['price_max', 'price_min'] as $column) {
            if ($this->columnExists('{{%catalog_price_categories}}', $column)) {
                $this->dropColumn('{{%catalog_price_categories}}', $column);
            }
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        $schema = $this->db->schema->getTableSchema($table, true);

        return $schema !== null && isset($schema->columns[$column]);
    }

    private function tableExists(string $table): bool
    {
        return $this->db->schema->getTableSchema($table, true) !== null;
    }

    private function indexExists(string $name): bool
    {
        $rawTable = $this->db->schema->getRawTableName('{{%catalog_fabric_collection_colors}}');
        $indexes = $this->db->createCommand('SHOW INDEX FROM `' . $rawTable . '` WHERE Key_name = :name', [':name' => $name])->queryAll();

        return $indexes !== [];
    }

    private function foreignKeyExists(string $name): bool
    {
        $rawTable = $this->db->schema->getRawTableName('{{%catalog_fabric_collection_colors}}');
        $rows = $this->db->createCommand(
            "SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE()
               AND TABLE_NAME = :table
               AND CONSTRAINT_NAME = :name
               AND CONSTRAINT_TYPE = 'FOREIGN KEY'",
            [':table' => $rawTable, ':name' => $name]
        )->queryAll();

        if ($rows !== []) {
            return true;
        }

        $collectionTable = $this->db->schema->getRawTableName('{{%catalog_fabric_collections}}');
        $rows = $this->db->createCommand(
            "SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE()
               AND TABLE_NAME = :table
               AND CONSTRAINT_NAME = :name
               AND CONSTRAINT_TYPE = 'FOREIGN KEY'",
            [':table' => $collectionTable, ':name' => $name]
        )->queryAll();

        if ($rows !== []) {
            return true;
        }

        if (!$this->tableExists('{{%catalog_import_media_queue}}')) {
            return false;
        }

        $queueTable = $this->db->schema->getRawTableName('{{%catalog_import_media_queue}}');

        return $this->db->createCommand(
            "SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE()
               AND TABLE_NAME = :table
               AND CONSTRAINT_NAME = :name
               AND CONSTRAINT_TYPE = 'FOREIGN KEY'",
            [':table' => $queueTable, ':name' => $name]
        )->queryOne() !== false;
    }
}
