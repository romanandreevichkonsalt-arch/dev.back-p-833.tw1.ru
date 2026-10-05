<?php

use yii\db\Migration;
use yii\db\Query;

class m260805_250000_catalog_dimension_template_images extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%catalog_dimension_template_images}}', [
            'id' => $this->primaryKey(),
            'template_id' => $this->integer()->notNull(),
            'media_file_id' => $this->integer()->notNull(),
            'sort_order' => $this->integer()->notNull()->defaultValue(0),
            'created_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex(
            'ux_catalog_dimension_template_images_template_media',
            '{{%catalog_dimension_template_images}}',
            ['template_id', 'media_file_id'],
            true
        );
        $this->createIndex(
            'idx_catalog_dimension_template_images_template_id',
            '{{%catalog_dimension_template_images}}',
            'template_id'
        );
        $this->addForeignKey(
            'fk_catalog_dimension_template_images_template_id',
            '{{%catalog_dimension_template_images}}',
            'template_id',
            '{{%catalog_dimension_templates}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
        $this->addForeignKey(
            'fk_catalog_dimension_template_images_media_file_id',
            '{{%catalog_dimension_template_images}}',
            'media_file_id',
            '{{%media_files}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        if ($this->db->schema->getTableSchema('{{%catalog_dimension_templates}}')->getColumn('image_id') !== null) {
            $now = date('Y-m-d H:i:s');
            foreach ((new Query())
                ->from('{{%catalog_dimension_templates}}')
                ->where(['not', ['image_id' => null]])
                ->all() as $row) {
                $this->insert('{{%catalog_dimension_template_images}}', [
                    'template_id' => (int)$row['id'],
                    'media_file_id' => (int)$row['image_id'],
                    'sort_order' => 0,
                    'created_at' => $now,
                ]);
            }

            $this->dropForeignKey('fk_catalog_dimension_templates_image_id', '{{%catalog_dimension_templates}}');
            $this->dropColumn('{{%catalog_dimension_templates}}', 'image_id');
        }
    }

    public function safeDown(): void
    {
        $this->addColumn('{{%catalog_dimension_templates}}', 'image_id', $this->integer()->null()->after('clearance'));
        $this->addForeignKey(
            'fk_catalog_dimension_templates_image_id',
            '{{%catalog_dimension_templates}}',
            'image_id',
            '{{%media_files}}',
            'id',
            'SET NULL',
            'CASCADE'
        );

        foreach ((new Query())
            ->from('{{%catalog_dimension_template_images}}')
            ->orderBy(['template_id' => SORT_ASC, 'sort_order' => SORT_ASC, 'id' => SORT_ASC])
            ->all() as $row) {
            $this->update(
                '{{%catalog_dimension_templates}}',
                ['image_id' => (int)$row['media_file_id']],
                ['id' => (int)$row['template_id'], 'image_id' => null]
            );
        }

        $this->dropForeignKey('fk_catalog_dimension_template_images_media_file_id', '{{%catalog_dimension_template_images}}');
        $this->dropForeignKey('fk_catalog_dimension_template_images_template_id', '{{%catalog_dimension_template_images}}');
        $this->dropTable('{{%catalog_dimension_template_images}}');
    }
}
