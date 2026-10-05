<?php

use yii\db\Migration;
use yii\db\Query;

class m260812_240000_catalog_hierarchy extends Migration
{
    public function safeUp(): void
    {
        $now = date('Y-m-d H:i:s');

        if ($this->tableExists('{{%catalog_groups}}') && !$this->tableExists('{{%catalog_directions}}')) {
            $this->renameTable('{{%catalog_groups}}', '{{%catalog_directions}}');
        }

        if ($this->columnExists('{{%catalog_collections}}', 'group_id')) {
            $this->dropForeignKeyIfExists('fk_catalog_collections_group_id', '{{%catalog_collections}}');
            $this->dropIndexIfExists('idx_catalog_collections_group_id', '{{%catalog_collections}}');
            $this->renameColumn('{{%catalog_collections}}', 'group_id', 'direction_id');
        }

        if (!$this->indexExists('idx_catalog_collections_direction_id', '{{%catalog_collections}}')) {
            $this->createIndex('idx_catalog_collections_direction_id', '{{%catalog_collections}}', 'direction_id');
        }
        $this->addForeignKeyIfNotExists(
            'fk_catalog_collections_direction_id',
            '{{%catalog_collections}}',
            'direction_id',
            '{{%catalog_directions}}',
            'id',
            'RESTRICT',
            'CASCADE'
        );

        if (!$this->tableExists('{{%catalog_categories}}')) {
            $this->createTable('{{%catalog_categories}}', [
                'id' => $this->primaryKey(),
                'collection_id' => $this->integer()->notNull(),
                'slug' => $this->string(64)->notNull(),
                'label' => $this->string(255)->notNull(),
                'sort_order' => $this->integer()->notNull()->defaultValue(0),
                'is_active' => $this->boolean()->notNull()->defaultValue(true),
                'created_at' => $this->dateTime()->notNull(),
                'updated_at' => $this->dateTime()->notNull(),
            ]);
            $this->createIndex('ux_catalog_categories_collection_slug', '{{%catalog_categories}}', ['collection_id', 'slug'], true);
            $this->addForeignKey(
                'fk_catalog_categories_collection_id',
                '{{%catalog_categories}}',
                'collection_id',
                '{{%catalog_collections}}',
                'id',
                'CASCADE',
                'CASCADE'
            );
        }

        $categoryIdByCollection = [];
        foreach ((new Query())->from('{{%catalog_collections}}')->orderBy(['id' => SORT_ASC])->all() as $collection) {
            $collectionId = (int)$collection['id'];
            $existingCategoryId = (new Query())
                ->select('id')
                ->from('{{%catalog_categories}}')
                ->where(['collection_id' => $collectionId, 'slug' => 'catalog'])
                ->scalar();

            if ($existingCategoryId) {
                $categoryIdByCollection[$collectionId] = (int)$existingCategoryId;
                continue;
            }

            $this->insert('{{%catalog_categories}}', [
                'collection_id' => $collectionId,
                'slug' => 'catalog',
                'label' => 'Каталог',
                'sort_order' => 0,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $categoryIdByCollection[$collectionId] = (int)$this->db->getLastInsertID();
        }

        if (!$this->columnExists('{{%catalog_subcategories}}', 'category_id')) {
            $this->addColumn('{{%catalog_subcategories}}', 'category_id', $this->integer()->null()->after('id'));
        }

        if ($this->columnExists('{{%catalog_subcategories}}', 'group_id')) {
            $this->alterColumn('{{%catalog_subcategories}}', 'group_id', $this->integer()->null());
        }

        $legacySubcategories = (new Query())
            ->from('{{%catalog_subcategories}}')
            ->where(['category_id' => null])
            ->andWhere(['not', ['group_id' => null]])
            ->orderBy(['id' => SORT_ASC])
            ->all();

        if ($legacySubcategories !== []) {
            $oldSubMap = [];
            foreach ($legacySubcategories as $oldSub) {
                $directionId = (int)$oldSub['group_id'];
                $collections = (new Query())
                    ->from('{{%catalog_collections}}')
                    ->where(['direction_id' => $directionId])
                    ->orderBy(['id' => SORT_ASC])
                    ->all();

                if ($collections === []) {
                    $collections = (new Query())
                        ->from('{{%catalog_collections}}')
                        ->orderBy(['id' => SORT_ASC])
                        ->limit(1)
                        ->all();
                }

                $newIdsByCollection = [];
                foreach ($collections as $collection) {
                    $collectionId = (int)$collection['id'];
                    $categoryId = $categoryIdByCollection[$collectionId] ?? null;
                    if ($categoryId === null) {
                        continue;
                    }

                    $this->insert('{{%catalog_subcategories}}', [
                        'category_id' => $categoryId,
                        'slug' => $oldSub['slug'],
                        'label' => $oldSub['label'],
                        'sort_order' => (int)$oldSub['sort_order'],
                        'is_active' => (bool)$oldSub['is_active'],
                        'created_at' => $oldSub['created_at'] ?? $now,
                        'updated_at' => $oldSub['updated_at'] ?? $now,
                    ]);
                    $newId = (int)$this->db->getLastInsertID();
                    $newIdsByCollection[$collectionId] = $newId;

                    $this->update(
                        '{{%catalog_models}}',
                        ['subcategory_id' => $newId],
                        [
                            'subcategory_id' => (int)$oldSub['id'],
                            'collection_id' => $collectionId,
                        ]
                    );
                    $this->update(
                        '{{%catalog_products}}',
                        ['subcategory_id' => $newId],
                        [
                            'subcategory_id' => (int)$oldSub['id'],
                            'collection_id' => $collectionId,
                        ]
                    );
                }

                $oldSubMap[(int)$oldSub['id']] = $newIdsByCollection;
            }

            foreach ($oldSubMap as $oldId => $newIdsByCollection) {
                if ($newIdsByCollection === []) {
                    continue;
                }
                $fallbackId = (int)reset($newIdsByCollection);
                $this->update('{{%catalog_models}}', ['subcategory_id' => $fallbackId], ['subcategory_id' => $oldId]);
                $this->update('{{%catalog_products}}', ['subcategory_id' => $fallbackId], ['subcategory_id' => $oldId]);
            }

            foreach (array_keys($oldSubMap) as $oldId) {
                $this->delete('{{%catalog_subcategories}}', ['id' => $oldId]);
            }
        }

        $this->alterColumn('{{%catalog_subcategories}}', 'category_id', $this->integer()->notNull());

        if ($this->columnExists('{{%catalog_subcategories}}', 'group_id')) {
            $this->dropForeignKeyIfExists('fk_catalog_subcategories_group_id', '{{%catalog_subcategories}}');
            $this->dropIndexIfExists('ux_catalog_subcategories_group_slug', '{{%catalog_subcategories}}');
            $this->dropColumn('{{%catalog_subcategories}}', 'group_id');
        }

        if (!$this->indexExists('ux_catalog_subcategories_category_slug', '{{%catalog_subcategories}}')) {
            $this->createIndex('ux_catalog_subcategories_category_slug', '{{%catalog_subcategories}}', ['category_id', 'slug'], true);
        }
        $this->addForeignKeyIfNotExists(
            'fk_catalog_subcategories_category_id',
            '{{%catalog_subcategories}}',
            'category_id',
            '{{%catalog_categories}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        if (!$this->columnExists('{{%catalog_models}}', 'category_id')) {
            $this->addColumn('{{%catalog_models}}', 'category_id', $this->integer()->null()->after('collection_id'));
        }

        $this->execute(
            'UPDATE {{%catalog_models}} m
             INNER JOIN {{%catalog_subcategories}} s ON s.id = m.subcategory_id
             SET m.category_id = s.category_id
             WHERE m.subcategory_id IS NOT NULL AND m.category_id IS NULL'
        );
        foreach ((new Query())->from('{{%catalog_models}}')->where(['category_id' => null])->all() as $model) {
            $categoryId = $categoryIdByCollection[(int)$model['collection_id']] ?? null;
            if ($categoryId !== null) {
                $this->update('{{%catalog_models}}', ['category_id' => $categoryId], ['id' => $model['id']]);
            }
        }

        $this->alterColumn('{{%catalog_models}}', 'category_id', $this->integer()->notNull());
        $this->addForeignKeyIfNotExists(
            'fk_catalog_models_category_id',
            '{{%catalog_models}}',
            'category_id',
            '{{%catalog_categories}}',
            'id',
            'RESTRICT',
            'CASCADE'
        );

        if ($this->columnExists('{{%catalog_models}}', 'type_id')) {
            $this->dropForeignKeyIfExists('fk_catalog_models_type_id', '{{%catalog_models}}');
            $this->dropColumn('{{%catalog_models}}', 'type_id');
        }

        if ($this->columnExists('{{%catalog_products}}', 'type_id')) {
            $this->dropForeignKeyIfExists('fk_catalog_products_type_id', '{{%catalog_products}}');
            $this->dropColumn('{{%catalog_products}}', 'type_id');
        }

        if ($this->tableExists('{{%catalog_types}}')) {
            $this->dropTable('{{%catalog_types}}');
        }
    }

    public function safeDown(): void
    {
        throw new \yii\base\NotSupportedException('Откат миграции catalog_hierarchy не поддерживается.');
    }

    private function tableExists(string $table): bool
    {
        return $this->db->getTableSchema($table, true) !== null;
    }

    private function columnExists(string $table, string $column): bool
    {
        $schema = $this->db->getTableSchema($table, true);

        return $schema !== null && isset($schema->columns[$column]);
    }

    private function indexExists(string $name, string $table): bool
    {
        $rawTable = $this->db->schema->getRawTableName($table);
        $indexes = $this->db->createCommand('SHOW INDEX FROM ' . $this->db->quoteTableName($rawTable))->queryAll();

        foreach ($indexes as $index) {
            if (($index['Key_name'] ?? '') === $name) {
                return true;
            }
        }

        return false;
    }

    private function dropForeignKeyIfExists(string $name, string $table): void
    {
        if (!$this->tableExists($table)) {
            return;
        }

        $rawTable = $this->db->schema->getRawTableName($table);
        $rows = $this->db->createCommand(
            'SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND CONSTRAINT_NAME = :name AND CONSTRAINT_TYPE = :type',
            [':table' => $rawTable, ':name' => $name, ':type' => 'FOREIGN KEY']
        )->queryAll();

        if ($rows !== []) {
            $this->dropForeignKey($name, $table);
        }
    }

    private function dropIndexIfExists(string $name, string $table): void
    {
        if ($this->indexExists($name, $table)) {
            $this->dropIndex($name, $table);
        }
    }

    private function addForeignKeyIfNotExists(
        string $name,
        string $table,
        string $columns,
        string $refTable,
        string $refColumns,
        ?string $delete = null,
        ?string $update = null
    ): void {
        $rawTable = $this->db->schema->getRawTableName($table);
        $exists = $this->db->createCommand(
            'SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND CONSTRAINT_NAME = :name AND CONSTRAINT_TYPE = :type',
            [':table' => $rawTable, ':name' => $name, ':type' => 'FOREIGN KEY']
        )->queryScalar();

        if (!$exists) {
            $this->addForeignKey($name, $table, $columns, $refTable, $refColumns, $delete, $update);
        }
    }
}
