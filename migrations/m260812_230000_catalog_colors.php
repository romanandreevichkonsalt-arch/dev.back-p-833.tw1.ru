<?php

use yii\db\Migration;

class m260812_230000_catalog_colors extends Migration
{
    public function safeUp(): void
    {
        $now = date('Y-m-d H:i:s');

        $this->createTable('{{%catalog_colors}}', [
            'id' => $this->primaryKey(),
            'slug' => $this->string(64)->notNull(),
            'label' => $this->string(255)->notNull(),
            'hex_color' => $this->string(7)->null(),
            'swatch_media_id' => $this->integer()->null(),
            'sort_order' => $this->integer()->notNull()->defaultValue(0),
            'is_active' => $this->boolean()->notNull()->defaultValue(true),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex('ux_catalog_colors_slug', '{{%catalog_colors}}', 'slug', true);
        $this->addForeignKey(
            'fk_catalog_colors_swatch_media_id',
            '{{%catalog_colors}}',
            'swatch_media_id',
            '{{%media_files}}',
            'id',
            'SET NULL',
            'CASCADE'
        );

        $this->createTable('{{%catalog_color_images}}', [
            'id' => $this->primaryKey(),
            'color_id' => $this->integer()->notNull(),
            'media_file_id' => $this->integer()->notNull(),
            'sort_order' => $this->integer()->notNull()->defaultValue(0),
            'created_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex('ux_catalog_color_images_color_media', '{{%catalog_color_images}}', ['color_id', 'media_file_id'], true);
        $this->addForeignKey(
            'fk_catalog_color_images_color_id',
            '{{%catalog_color_images}}',
            'color_id',
            '{{%catalog_colors}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
        $this->addForeignKey(
            'fk_catalog_color_images_media_file_id',
            '{{%catalog_color_images}}',
            'media_file_id',
            '{{%media_files}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->createTable('{{%catalog_fabric_collection_colors}}', [
            'id' => $this->primaryKey(),
            'fabric_collection_id' => $this->integer()->notNull(),
            'color_id' => $this->integer()->notNull(),
            'sort_order' => $this->integer()->notNull()->defaultValue(0),
            'is_active' => $this->boolean()->notNull()->defaultValue(true),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex(
            'ux_catalog_fabric_collection_colors_pair',
            '{{%catalog_fabric_collection_colors}}',
            ['fabric_collection_id', 'color_id'],
            true
        );
        $this->addForeignKey(
            'fk_catalog_fcc_fabric_collection_id',
            '{{%catalog_fabric_collection_colors}}',
            'fabric_collection_id',
            '{{%catalog_fabric_collections}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
        $this->addForeignKey(
            'fk_catalog_fcc_color_id',
            '{{%catalog_fabric_collection_colors}}',
            'color_id',
            '{{%catalog_colors}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $colorIdByKey = [];
        $slugCounters = [];
        $oldToNewLinkId = [];

        $legacyColors = (new \yii\db\Query())
            ->from('{{%catalog_fabric_colors}}')
            ->orderBy(['id' => SORT_ASC])
            ->all();

        foreach ($legacyColors as $row) {
            $label = trim((string)$row['label']);
            $hex = strtolower(trim((string)($row['hex_color'] ?? '')));
            $dedupKey = mb_strtolower($label) . '|' . $hex;

            if (!isset($colorIdByKey[$dedupKey])) {
                $baseSlug = $this->slugify($label !== '' ? $label : 'color');
                $slug = $this->uniqueSlug($baseSlug, $slugCounters);

                $this->insert('{{%catalog_colors}}', [
                    'slug' => $slug,
                    'label' => $label !== '' ? $label : 'Цвет',
                    'hex_color' => $row['hex_color'] ?: null,
                    'swatch_media_id' => $row['swatch_media_id'] ?: null,
                    'sort_order' => (int)$row['sort_order'],
                    'is_active' => (bool)$row['is_active'],
                    'created_at' => $row['created_at'] ?? $now,
                    'updated_at' => $row['updated_at'] ?? $now,
                ]);
                $colorIdByKey[$dedupKey] = (int)$this->db->getLastInsertID();
            }

            $catalogColorId = $colorIdByKey[$dedupKey];

            $this->insert('{{%catalog_fabric_collection_colors}}', [
                'fabric_collection_id' => (int)$row['fabric_collection_id'],
                'color_id' => $catalogColorId,
                'sort_order' => (int)$row['sort_order'],
                'is_active' => (bool)$row['is_active'],
                'created_at' => $row['created_at'] ?? $now,
                'updated_at' => $row['updated_at'] ?? $now,
            ]);
            $oldToNewLinkId[(int)$row['id']] = (int)$this->db->getLastInsertID();
        }

        $legacyImages = (new \yii\db\Query())
            ->from('{{%catalog_fabric_color_images}}')
            ->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC])
            ->all();

        $imageKeys = [];
        foreach ($legacyImages as $imageRow) {
            $oldColorId = (int)$imageRow['fabric_color_id'];
            $legacyColor = (new \yii\db\Query())
                ->from('{{%catalog_fabric_colors}}')
                ->where(['id' => $oldColorId])
                ->one();
            if ($legacyColor === false) {
                continue;
            }

            $label = trim((string)$legacyColor['label']);
            $hex = strtolower(trim((string)($legacyColor['hex_color'] ?? '')));
            $dedupKey = mb_strtolower($label) . '|' . $hex;
            if (!isset($colorIdByKey[$dedupKey])) {
                continue;
            }

            $colorId = $colorIdByKey[$dedupKey];
            $mediaId = (int)$imageRow['media_file_id'];
            $imageKey = $colorId . ':' . $mediaId;
            if (isset($imageKeys[$imageKey])) {
                continue;
            }
            $imageKeys[$imageKey] = true;

            $this->insert('{{%catalog_color_images}}', [
                'color_id' => $colorId,
                'media_file_id' => $mediaId,
                'sort_order' => (int)$imageRow['sort_order'],
                'created_at' => $imageRow['created_at'] ?? $now,
            ]);
        }

        $this->dropForeignKey('fk_catalog_products_fabric_color_id', '{{%catalog_products}}');

        foreach ($oldToNewLinkId as $oldId => $newId) {
            $this->update('{{%catalog_products}}', ['fabric_color_id' => $newId], ['fabric_color_id' => $oldId]);
        }

        $this->dropForeignKey('fk_catalog_fabric_color_images_fabric_color_id', '{{%catalog_fabric_color_images}}');
        $this->dropForeignKey('fk_catalog_fabric_color_images_media_file_id', '{{%catalog_fabric_color_images}}');
        $this->dropTable('{{%catalog_fabric_color_images}}');

        $this->dropForeignKey('fk_catalog_fabric_colors_fabric_collection_id', '{{%catalog_fabric_colors}}');
        $this->dropTable('{{%catalog_fabric_colors}}');

        $this->addForeignKey(
            'fk_catalog_products_fabric_color_id',
            '{{%catalog_products}}',
            'fabric_color_id',
            '{{%catalog_fabric_collection_colors}}',
            'id',
            'SET NULL',
            'CASCADE'
        );
    }

    public function safeDown(): void
    {
        throw new \yii\base\NotSupportedException('Откат миграции catalog_colors не поддерживается.');
    }

    /**
     * @param array<string, int> $slugCounters
     */
    private function uniqueSlug(string $base, array &$slugCounters): string
    {
        if (!isset($slugCounters[$base])) {
            $slugCounters[$base] = 0;

            return $base;
        }

        $slugCounters[$base]++;
        $candidate = $base . '-' . $slugCounters[$base];
        while (isset($slugCounters[$candidate])) {
            $slugCounters[$base]++;
            $candidate = $base . '-' . $slugCounters[$base];
        }
        $slugCounters[$candidate] = 0;

        return $candidate;
    }

    private function slugify(string $value): string
    {
        $slug = \app\helpers\SlugHelper::slugify($value);

        return $slug !== '' ? $slug : 'color';
    }
}
