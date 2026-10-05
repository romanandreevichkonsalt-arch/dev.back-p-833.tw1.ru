<?php

use yii\db\Migration;
use yii\db\Query;

class m260806_130000_catalog_fabric_attribute_groups extends Migration
{
    public function safeUp(): void
    {
        $now = date('Y-m-d H:i:s');

        $this->createTable('{{%catalog_fabric_attribute_groups}}', [
            'id' => $this->primaryKey(),
            'slug' => $this->string(64)->notNull()->unique(),
            'label' => $this->string(255)->notNull(),
            'sort_order' => $this->integer()->notNull()->defaultValue(0),
            'is_active' => $this->boolean()->notNull()->defaultValue(true),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);

        $this->createTable('{{%catalog_fabric_attributes}}', [
            'id' => $this->primaryKey(),
            'group_id' => $this->integer()->notNull(),
            'slug' => $this->string(64)->notNull(),
            'label' => $this->string(255)->notNull(),
            'sort_order' => $this->integer()->notNull()->defaultValue(0),
            'is_active' => $this->boolean()->notNull()->defaultValue(true),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex('ux_catalog_fabric_attributes_group_slug', '{{%catalog_fabric_attributes}}', ['group_id', 'slug'], true);
        $this->addForeignKey(
            'fk_catalog_fabric_attributes_group_id',
            '{{%catalog_fabric_attributes}}',
            'group_id',
            '{{%catalog_fabric_attribute_groups}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->createTable('{{%catalog_fabric_attribute_links}}', [
            'id' => $this->primaryKey(),
            'fabric_id' => $this->integer()->notNull(),
            'attribute_id' => $this->integer()->notNull(),
            'created_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex('ux_catalog_fabric_attribute_links_fabric_attribute', '{{%catalog_fabric_attribute_links}}', ['fabric_id', 'attribute_id'], true);
        $this->createIndex('idx_catalog_fabric_attribute_links_fabric_id', '{{%catalog_fabric_attribute_links}}', 'fabric_id');
        $this->createIndex('idx_catalog_fabric_attribute_links_attribute_id', '{{%catalog_fabric_attribute_links}}', 'attribute_id');
        $this->addForeignKey(
            'fk_catalog_fabric_attribute_links_fabric_id',
            '{{%catalog_fabric_attribute_links}}',
            'fabric_id',
            '{{%catalog_fabrics}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
        $this->addForeignKey(
            'fk_catalog_fabric_attribute_links_attribute_id',
            '{{%catalog_fabric_attribute_links}}',
            'attribute_id',
            '{{%catalog_fabric_attributes}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $textureGroupId = $this->insertGroup('texture', 'Фактура', 0, $now);
        $colorGroupId = $this->insertGroup('color', 'Цвет', 1, $now);

        $textureAttributeMap = [];
        if ($this->db->getTableSchema('{{%catalog_fabric_textures}}') !== null) {
            foreach ((new Query())->from('{{%catalog_fabric_textures}}')->orderBy(['sort_order' => SORT_ASC])->all() as $row) {
                $attrId = $this->insertAttribute((int)$textureGroupId, (string)$row['slug'], (string)$row['label'], (int)$row['sort_order'], $now);
                $textureAttributeMap[(int)$row['id']] = $attrId;
            }
        }

        if ($textureAttributeMap === []) {
            $this->insertAttribute((int)$textureGroupId, 'velour', 'Велюр', 0, $now);
        }

        $colorAttributeMap = [];
        foreach ((new Query())->from('{{%catalog_fabrics}}')->select('color_label')->distinct()->column() as $colorLabel) {
            $colorLabel = trim((string)$colorLabel);
            if ($colorLabel === '') {
                continue;
            }
            $slug = $this->slugify($colorLabel, 'color');
            $colorAttributeMap[$colorLabel] = $this->insertAttribute((int)$colorGroupId, $slug, $colorLabel, count($colorAttributeMap), $now);
        }

        foreach ((new Query())->from('{{%catalog_fabrics}}')->all() as $fabric) {
            $fabricId = (int)$fabric['id'];
            $textureId = isset($fabric['texture_id']) ? (int)$fabric['texture_id'] : 0;
            if ($textureId > 0 && isset($textureAttributeMap[$textureId])) {
                $this->linkAttribute($fabricId, (int)$textureAttributeMap[$textureId], $now);
            }

            $colorLabel = trim((string)($fabric['color_label'] ?? ''));
            if ($colorLabel !== '' && isset($colorAttributeMap[$colorLabel])) {
                $this->linkAttribute($fabricId, (int)$colorAttributeMap[$colorLabel], $now);
            }
        }

        if ($this->db->getTableSchema('{{%catalog_fabrics}}')->getColumn('texture_id') !== null) {
            $this->dropForeignKey('fk_catalog_fabrics_texture_id', '{{%catalog_fabrics}}');
            $this->dropIndex('idx_catalog_fabrics_texture_id', '{{%catalog_fabrics}}');
            $this->dropColumn('{{%catalog_fabrics}}', 'texture_id');
        }

        if ($this->db->getTableSchema('{{%catalog_fabrics}}')->getColumn('color_label') !== null) {
            $this->dropColumn('{{%catalog_fabrics}}', 'color_label');
        }

        if ($this->db->getTableSchema('{{%catalog_fabric_textures}}') !== null) {
            $this->dropTable('{{%catalog_fabric_textures}}');
        }
    }

    public function safeDown(): void
    {
        $now = date('Y-m-d H:i:s');

        $this->createTable('{{%catalog_fabric_textures}}', [
            'id' => $this->primaryKey(),
            'slug' => $this->string(64)->notNull()->unique(),
            'label' => $this->string(255)->notNull(),
            'sort_order' => $this->integer()->notNull()->defaultValue(0),
            'is_active' => $this->boolean()->notNull()->defaultValue(true),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);

        $this->addColumn('{{%catalog_fabrics}}', 'color_label', $this->string(128)->notNull()->defaultValue(''));
        $this->addColumn('{{%catalog_fabrics}}', 'texture_id', $this->integer()->null()->after('color_label'));

        $this->dropForeignKey('fk_catalog_fabric_attribute_links_attribute_id', '{{%catalog_fabric_attribute_links}}');
        $this->dropForeignKey('fk_catalog_fabric_attribute_links_fabric_id', '{{%catalog_fabric_attribute_links}}');
        $this->dropTable('{{%catalog_fabric_attribute_links}}');

        $this->dropForeignKey('fk_catalog_fabric_attributes_group_id', '{{%catalog_fabric_attributes}}');
        $this->dropTable('{{%catalog_fabric_attributes}}');
        $this->dropTable('{{%catalog_fabric_attribute_groups}}');
    }

    private function insertGroup(string $slug, string $label, int $sortOrder, string $now): int
    {
        $this->insert('{{%catalog_fabric_attribute_groups}}', [
            'slug' => $slug,
            'label' => $label,
            'sort_order' => $sortOrder,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int)$this->db->getLastInsertID();
    }

    private function insertAttribute(int $groupId, string $slug, string $label, int $sortOrder, string $now): int
    {
        $exists = (new Query())
            ->from('{{%catalog_fabric_attributes}}')
            ->where(['group_id' => $groupId, 'slug' => $slug])
            ->exists();
        if ($exists) {
            return (int)(new Query())
                ->from('{{%catalog_fabric_attributes}}')
                ->where(['group_id' => $groupId, 'slug' => $slug])
                ->select('id')
                ->scalar();
        }

        $this->insert('{{%catalog_fabric_attributes}}', [
            'group_id' => $groupId,
            'slug' => substr($slug, 0, 64),
            'label' => $label,
            'sort_order' => $sortOrder,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int)$this->db->getLastInsertID();
    }

    private function linkAttribute(int $fabricId, int $attributeId, string $now): void
    {
        $exists = (new Query())
            ->from('{{%catalog_fabric_attribute_links}}')
            ->where(['fabric_id' => $fabricId, 'attribute_id' => $attributeId])
            ->exists();
        if ($exists) {
            return;
        }

        $this->insert('{{%catalog_fabric_attribute_links}}', [
            'fabric_id' => $fabricId,
            'attribute_id' => $attributeId,
            'created_at' => $now,
        ]);
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
