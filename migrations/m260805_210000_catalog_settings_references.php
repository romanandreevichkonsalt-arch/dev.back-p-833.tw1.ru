<?php

use yii\db\Migration;
use yii\db\Query;

class m260805_210000_catalog_settings_references extends Migration
{
    public function safeUp(): void
    {
        $now = date('Y-m-d H:i:s');

        $this->createTable('{{%catalog_types}}', [
            'id' => $this->primaryKey(),
            'slug' => $this->string(64)->notNull()->unique(),
            'label' => $this->string(255)->notNull(),
            'sort_order' => $this->integer()->notNull()->defaultValue(0),
            'is_active' => $this->boolean()->notNull()->defaultValue(true),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);

        $this->createTable('{{%catalog_layouts}}', [
            'id' => $this->primaryKey(),
            'slug' => $this->string(64)->notNull()->unique(),
            'label' => $this->string(255)->notNull(),
            'description' => $this->text()->null(),
            'preview_image_id' => $this->integer()->null(),
            'sort_order' => $this->integer()->notNull()->defaultValue(0),
            'is_active' => $this->boolean()->notNull()->defaultValue(true),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);
        $this->addForeignKey(
            'fk_catalog_layouts_preview_image_id',
            '{{%catalog_layouts}}',
            'preview_image_id',
            '{{%media_files}}',
            'id',
            'SET NULL',
            'CASCADE'
        );

        $this->createTable('{{%catalog_badges}}', [
            'id' => $this->primaryKey(),
            'slug' => $this->string(64)->notNull()->unique(),
            'label' => $this->string(255)->notNull(),
            'variant' => $this->string(32)->notNull()->defaultValue('hit'),
            'image_id' => $this->integer()->null(),
            'sort_order' => $this->integer()->notNull()->defaultValue(0),
            'is_active' => $this->boolean()->notNull()->defaultValue(true),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);
        $this->addForeignKey(
            'fk_catalog_badges_image_id',
            '{{%catalog_badges}}',
            'image_id',
            '{{%media_files}}',
            'id',
            'SET NULL',
            'CASCADE'
        );

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

        $this->addColumn('{{%catalog_collections}}', 'group_id', $this->integer()->null()->after('id'));
        $this->addColumn('{{%catalog_collections}}', 'name', $this->string(255)->null()->after('slug'));
        $this->createIndex('idx_catalog_collections_group_id', '{{%catalog_collections}}', 'group_id');
        $this->addForeignKey(
            'fk_catalog_collections_group_id',
            '{{%catalog_collections}}',
            'group_id',
            '{{%catalog_groups}}',
            'id',
            'RESTRICT',
            'CASCADE'
        );

        $this->addColumn('{{%catalog_products}}', 'type_id', $this->integer()->null()->after('collection_id'));
        $this->addColumn('{{%catalog_products}}', 'badge_id', $this->integer()->null()->after('image_position'));
        $this->addColumn('{{%catalog_products}}', 'layout_id', $this->integer()->null()->after('swatch_count'));
        $this->addColumn('{{%catalog_products}}', 'dimension_template_id', $this->integer()->null()->after('layout_id'));
        $this->addColumn('{{%catalog_products}}', 'overall_size', $this->string(64)->null()->after('dimension_template_id'));
        $this->addColumn('{{%catalog_products}}', 'seat_depth', $this->string(64)->null()->after('overall_size'));
        $this->addColumn('{{%catalog_products}}', 'seat_height', $this->string(64)->null()->after('seat_depth'));
        $this->addColumn('{{%catalog_products}}', 'armrest_width', $this->string(64)->null()->after('seat_height'));
        $this->addColumn('{{%catalog_products}}', 'clearance', $this->string(64)->null()->after('armrest_width'));

        $this->addForeignKey('fk_catalog_products_type_id', '{{%catalog_products}}', 'type_id', '{{%catalog_types}}', 'id', 'SET NULL', 'CASCADE');
        $this->addForeignKey('fk_catalog_products_badge_id', '{{%catalog_products}}', 'badge_id', '{{%catalog_badges}}', 'id', 'SET NULL', 'CASCADE');
        $this->addForeignKey('fk_catalog_products_layout_id', '{{%catalog_products}}', 'layout_id', '{{%catalog_layouts}}', 'id', 'SET NULL', 'CASCADE');
        $this->addForeignKey(
            'fk_catalog_products_dimension_template_id',
            '{{%catalog_products}}',
            'dimension_template_id',
            '{{%catalog_dimension_templates}}',
            'id',
            'SET NULL',
            'CASCADE'
        );

        $defaultGroupId = (new Query())->from('{{%catalog_groups}}')->select('id')->orderBy(['id' => SORT_ASC])->scalar();
        if ($defaultGroupId) {
            $this->update('{{%catalog_collections}}', ['group_id' => (int)$defaultGroupId], ['group_id' => null]);
        }

        foreach ((new Query())->from('{{%catalog_collections}}')->all() as $row) {
            $name = trim((string)($row['label'] ?? '') . ' ' . (string)($row['title'] ?? ''));
            if ($name === '') {
                $name = (string)($row['title'] ?? $row['slug']);
            }
            $this->update('{{%catalog_collections}}', ['name' => $name], ['id' => $row['id']]);
        }

        $layoutMap = [];
        foreach (['featured' => 'Featured', 'stacked' => 'Stacked', 'compact' => 'Compact'] as $slug => $label) {
            $this->insert('{{%catalog_layouts}}', [
                'slug' => $slug,
                'label' => $label,
                'description' => null,
                'preview_image_id' => null,
                'sort_order' => count($layoutMap),
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $layoutMap[$slug] = (int)$this->db->getLastInsertID();
        }

        $typeMap = [];
        $typeLabels = (new Query())
            ->from('{{%catalog_products}}')
            ->select('type_label')
            ->where(['not', ['type_label' => null]])
            ->andWhere(['<>', 'type_label', ''])
            ->distinct()
            ->column();
        foreach ($typeLabels as $index => $label) {
            $slug = $this->slugify((string)$label, 'type-' . ($index + 1));
            $this->insert('{{%catalog_types}}', [
                'slug' => $slug,
                'label' => $label,
                'sort_order' => $index,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $typeMap[$label] = (int)$this->db->getLastInsertID();
        }

        $badgeMap = [];
        $badges = (new Query())
            ->from('{{%catalog_products}}')
            ->select(['badge_text', 'badge_variant'])
            ->where(['not', ['badge_text' => null]])
            ->andWhere(['<>', 'badge_text', ''])
            ->distinct()
            ->all();
        foreach ($badges as $index => $badge) {
            $text = (string)$badge['badge_text'];
            $variant = (string)($badge['badge_variant'] ?: 'hit');
            $key = $text . '|' . $variant;
            if (isset($badgeMap[$key])) {
                continue;
            }
            $slug = $this->slugify($text, 'badge-' . ($index + 1));
            $this->insert('{{%catalog_badges}}', [
                'slug' => $slug,
                'label' => $text,
                'variant' => $variant,
                'image_id' => null,
                'sort_order' => count($badgeMap),
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $badgeMap[$key] = (int)$this->db->getLastInsertID();
        }

        foreach ((new Query())->from('{{%catalog_products}}')->all() as $product) {
            $updates = [];
            if (!empty($product['type_label']) && isset($typeMap[$product['type_label']])) {
                $updates['type_id'] = $typeMap[$product['type_label']];
            }
            if (!empty($product['layout']) && isset($layoutMap[$product['layout']])) {
                $updates['layout_id'] = $layoutMap[$product['layout']];
            }
            if (!empty($product['badge_text'])) {
                $key = $product['badge_text'] . '|' . ($product['badge_variant'] ?: 'hit');
                if (isset($badgeMap[$key])) {
                    $updates['badge_id'] = $badgeMap[$key];
                }
            }
            if ($updates !== []) {
                $this->update('{{%catalog_products}}', $updates, ['id' => $product['id']]);
            }
        }

        $this->dropColumn('{{%catalog_products}}', 'type_label');
        $this->dropColumn('{{%catalog_products}}', 'collection_label');
        $this->dropColumn('{{%catalog_products}}', 'badge_text');
        $this->dropColumn('{{%catalog_products}}', 'badge_variant');
        $this->dropColumn('{{%catalog_products}}', 'layout');
    }

    public function safeDown(): void
    {
        $this->addColumn('{{%catalog_products}}', 'type_label', $this->string(128)->null());
        $this->addColumn('{{%catalog_products}}', 'collection_label', $this->string(255)->null());
        $this->addColumn('{{%catalog_products}}', 'badge_text', $this->string(64)->null());
        $this->addColumn('{{%catalog_products}}', 'badge_variant', $this->string(32)->null());
        $this->addColumn('{{%catalog_products}}', 'layout', $this->string(32)->null());

        $this->dropForeignKey('fk_catalog_products_dimension_template_id', '{{%catalog_products}}');
        $this->dropForeignKey('fk_catalog_products_layout_id', '{{%catalog_products}}');
        $this->dropForeignKey('fk_catalog_products_badge_id', '{{%catalog_products}}');
        $this->dropForeignKey('fk_catalog_products_type_id', '{{%catalog_products}}');

        $this->dropColumn('{{%catalog_products}}', 'clearance');
        $this->dropColumn('{{%catalog_products}}', 'armrest_width');
        $this->dropColumn('{{%catalog_products}}', 'seat_height');
        $this->dropColumn('{{%catalog_products}}', 'seat_depth');
        $this->dropColumn('{{%catalog_products}}', 'overall_size');
        $this->dropColumn('{{%catalog_products}}', 'dimension_template_id');
        $this->dropColumn('{{%catalog_products}}', 'layout_id');
        $this->dropColumn('{{%catalog_products}}', 'badge_id');
        $this->dropColumn('{{%catalog_products}}', 'type_id');

        $this->dropForeignKey('fk_catalog_collections_group_id', '{{%catalog_collections}}');
        $this->dropIndex('idx_catalog_collections_group_id', '{{%catalog_collections}}');
        $this->dropColumn('{{%catalog_collections}}', 'name');
        $this->dropColumn('{{%catalog_collections}}', 'group_id');

        $this->dropTable('{{%catalog_dimension_templates}}');
        $this->dropForeignKey('fk_catalog_badges_image_id', '{{%catalog_badges}}');
        $this->dropTable('{{%catalog_badges}}');
        $this->dropForeignKey('fk_catalog_layouts_preview_image_id', '{{%catalog_layouts}}');
        $this->dropTable('{{%catalog_layouts}}');
        $this->dropTable('{{%catalog_types}}');
    }

    private function slugify(string $value, string $fallback): string
    {
        $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $value) ?? '', '-'));
        if ($slug === '') {
            return $fallback;
        }

        return substr($slug, 0, 64);
    }
}
