<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * Категории каталога — единый справочник для всех коллекций (без collection_id).
 */
class m260814_180000_catalog_global_categories extends Migration
{
    public function safeUp(): void
    {
        $now = date('Y-m-d H:i:s');

        if ($this->columnExists('{{%catalog_categories}}', 'collection_id')) {
            $this->alterColumn('{{%catalog_categories}}', 'collection_id', $this->integer()->null());
        }

        $categoryDefs = [
            ['slug' => 'sofa', 'label' => 'Диван', 'sort_order' => 0],
            ['slug' => 'armchair', 'label' => 'Кресло', 'sort_order' => 1],
            ['slug' => 'combination', 'label' => 'Комбинация', 'sort_order' => 2],
        ];

        $subcategoryDefs = [
            'sofa' => [
                ['slug' => 'straight', 'label' => 'Прямой диван', 'sort_order' => 0],
                ['slug' => 'corner', 'label' => 'Угловой диван', 'sort_order' => 1],
                ['slug' => 'compact', 'label' => 'Малогабаритный диван', 'sort_order' => 2],
                ['slug' => 'modular', 'label' => 'Модульный диван', 'sort_order' => 3],
            ],
            'armchair' => [
                ['slug' => 'armchair', 'label' => 'Кресло', 'sort_order' => 0],
                ['slug' => 'chair-bed', 'label' => 'Кресло-кровать', 'sort_order' => 1],
            ],
        ];

        $categoryIdsBySlug = [];
        foreach ($categoryDefs as $def) {
            $existingId = (new Query())
                ->from('{{%catalog_categories}}')
                ->select('id')
                ->where(['slug' => $def['slug']])
                ->scalar();

            if ($existingId) {
                $categoryIdsBySlug[$def['slug']] = (int)$existingId;
                $this->update('{{%catalog_categories}}', [
                    'label' => $def['label'],
                    'sort_order' => $def['sort_order'],
                    'is_active' => true,
                    'updated_at' => $now,
                ], ['id' => (int)$existingId]);

                continue;
            }

            $this->insert('{{%catalog_categories}}', [
                'slug' => $def['slug'],
                'label' => $def['label'],
                'sort_order' => $def['sort_order'],
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $categoryIdsBySlug[$def['slug']] = (int)$this->db->getLastInsertID();
        }

        $subcategoryIdsBySlug = [];
        foreach ($subcategoryDefs as $categorySlug => $subs) {
            $categoryId = $categoryIdsBySlug[$categorySlug] ?? null;
            if ($categoryId === null) {
                continue;
            }

            foreach ($subs as $sub) {
                $existingSubId = (new Query())
                    ->from('{{%catalog_subcategories}}')
                    ->select('id')
                    ->where(['category_id' => $categoryId, 'slug' => $sub['slug']])
                    ->scalar();

                if ($existingSubId) {
                    $subcategoryIdsBySlug[$sub['slug']] = (int)$existingSubId;
                    $this->update('{{%catalog_subcategories}}', [
                        'label' => $sub['label'],
                        'sort_order' => $sub['sort_order'],
                        'is_active' => true,
                    ], ['id' => (int)$existingSubId]);

                    continue;
                }

                $legacySubId = (new Query())
                    ->from('{{%catalog_subcategories}}')
                    ->select('id')
                    ->where(['slug' => $sub['slug']])
                    ->orderBy(['id' => SORT_ASC])
                    ->scalar();

                if ($legacySubId) {
                    $subcategoryIdsBySlug[$sub['slug']] = (int)$legacySubId;
                    $this->update('{{%catalog_subcategories}}', [
                        'category_id' => $categoryId,
                        'label' => $sub['label'],
                        'sort_order' => $sub['sort_order'],
                        'is_active' => true,
                    ], ['id' => (int)$legacySubId]);

                    continue;
                }

                $this->insert('{{%catalog_subcategories}}', [
                    'category_id' => $categoryId,
                    'slug' => $sub['slug'],
                    'label' => $sub['label'],
                    'sort_order' => $sub['sort_order'],
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $subcategoryIdsBySlug[$sub['slug']] = (int)$this->db->getLastInsertID();
            }
        }

        $legacySlugMap = [
            'straight' => 'straight',
            'corner' => 'corner',
            'compact' => 'compact',
            'modular' => 'modular',
            'armchairs' => 'armchair',
            'chair-beds' => 'chair-bed',
        ];

        $oldSubs = (new Query())
            ->from('{{%catalog_subcategories}}')
            ->select(['id', 'slug', 'category_id'])
            ->all();

        foreach ($oldSubs as $oldSub) {
            $oldId = (int)$oldSub['id'];
            $slug = (string)$oldSub['slug'];
            $targetSlug = $legacySlugMap[$slug] ?? $slug;
            $newSubId = $subcategoryIdsBySlug[$targetSlug] ?? null;
            if ($newSubId === null || $newSubId === $oldId) {
                continue;
            }

            $newCategoryId = (new Query())
                ->from('{{%catalog_subcategories}}')
                ->select('category_id')
                ->where(['id' => $newSubId])
                ->scalar();

            $this->update('{{%catalog_models}}', ['subcategory_id' => $newSubId], ['subcategory_id' => $oldId]);
            $this->update('{{%catalog_products}}', ['subcategory_id' => $newSubId], ['subcategory_id' => $oldId]);

            if ($newCategoryId) {
                $this->update('{{%catalog_models}}', ['category_id' => (int)$newCategoryId], ['subcategory_id' => $newSubId]);
            }
        }

        $keepCategoryIds = array_values($categoryIdsBySlug);
        $keepSubcategoryIds = array_values($subcategoryIdsBySlug);

        if ($keepSubcategoryIds !== []) {
            $this->delete('{{%catalog_subcategories}}', ['not in', 'id', $keepSubcategoryIds]);
        }

        $this->execute(
            'UPDATE {{%catalog_models}} m
             INNER JOIN {{%catalog_subcategories}} s ON s.id = m.subcategory_id
             SET m.category_id = s.category_id
             WHERE m.subcategory_id IS NOT NULL'
        );

        if ($keepCategoryIds !== []) {
            $fallbackCategoryId = $categoryIdsBySlug['sofa'] ?? $keepCategoryIds[0];
            $this->update(
                '{{%catalog_models}}',
                ['category_id' => (int)$fallbackCategoryId],
                ['not in', 'category_id', $keepCategoryIds]
            );
        }

        if ($keepCategoryIds !== []) {
            $this->delete('{{%catalog_categories}}', ['not in', 'id', $keepCategoryIds]);
        }

        if ($this->columnExists('{{%catalog_categories}}', 'collection_id')) {
            $this->dropForeignKeyIfExists('fk_catalog_categories_collection_id', '{{%catalog_categories}}');
            $this->dropIndexIfExists('ux_catalog_categories_collection_slug', '{{%catalog_categories}}');
            $this->dropColumn('{{%catalog_categories}}', 'collection_id');
        }

        if (!$this->indexExists('ux_catalog_categories_slug', '{{%catalog_categories}}')) {
            $this->createIndex('ux_catalog_categories_slug', '{{%catalog_categories}}', 'slug', true);
        }
    }

    public function safeDown(): void
    {
        $this->dropIndexIfExists('ux_catalog_categories_slug', '{{%catalog_categories}}');

        if (!$this->columnExists('{{%catalog_categories}}', 'collection_id')) {
            $this->addColumn('{{%catalog_categories}}', 'collection_id', $this->integer()->null()->after('id'));
        }

        $collections = (new Query())->from('{{%catalog_collections}}')->select(['id'])->column();
        $now = date('Y-m-d H:i:s');
        foreach ($collections as $collectionId) {
            $exists = (new Query())
                ->from('{{%catalog_categories}}')
                ->where(['collection_id' => (int)$collectionId])
                ->exists();
            if ($exists) {
                continue;
            }
            $this->insert('{{%catalog_categories}}', [
                'collection_id' => (int)$collectionId,
                'slug' => 'catalog',
                'label' => 'Каталог',
                'sort_order' => 0,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

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

    private function columnExists(string $table, string $column): bool
    {
        $schema = $this->db->schema->getTableSchema($table, true);

        return $schema !== null && isset($schema->columns[$column]);
    }

    private function indexExists(string $name, string $table): bool
    {
        $raw = $this->db->schema->getRawTableName($table);
        $indexes = $this->db->schema->getTableIndexes($raw, true);

        return isset($indexes[$name]);
    }

    private function dropForeignKeyIfExists(string $name, string $table): void
    {
        $raw = $this->db->schema->getRawTableName($table);
        foreach ($this->db->schema->getTableForeignKeys($raw, true) as $fk) {
            if ($fk->name === $name) {
                $this->dropForeignKey($name, $table);
                return;
            }
        }
    }

    private function dropIndexIfExists(string $name, string $table): void
    {
        if ($this->indexExists($name, $table)) {
            $this->dropIndex($name, $table);
        }
    }
}
