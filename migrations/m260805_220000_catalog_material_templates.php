<?php

use yii\db\Migration;

class m260805_220000_catalog_material_templates extends Migration
{
    public function safeUp(): void
    {
        $now = date('Y-m-d H:i:s');

        $this->createTable('{{%catalog_material_templates}}', [
            'id' => $this->primaryKey(),
            'slug' => $this->string(64)->notNull()->unique(),
            'name' => $this->string(255)->notNull(),
            'frame' => $this->text()->null(),
            'foundation' => $this->text()->null(),
            'filling' => $this->text()->null(),
            'upholstery' => $this->text()->null(),
            'supports' => $this->text()->null(),
            'sort_order' => $this->integer()->notNull()->defaultValue(0),
            'is_active' => $this->boolean()->notNull()->defaultValue(true),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);

        $this->addColumn('{{%catalog_products}}', 'material_template_id', $this->integer()->null()->after('clearance'));
        $this->addColumn('{{%catalog_products}}', 'frame', $this->text()->null()->after('material_template_id'));
        $this->addColumn('{{%catalog_products}}', 'foundation', $this->text()->null()->after('frame'));
        $this->addColumn('{{%catalog_products}}', 'filling', $this->text()->null()->after('foundation'));
        $this->addColumn('{{%catalog_products}}', 'upholstery', $this->text()->null()->after('filling'));
        $this->addColumn('{{%catalog_products}}', 'supports', $this->text()->null()->after('upholstery'));

        $this->addForeignKey(
            'fk_catalog_products_material_template_id',
            '{{%catalog_products}}',
            'material_template_id',
            '{{%catalog_material_templates}}',
            'id',
            'SET NULL',
            'CASCADE'
        );
    }

    public function safeDown(): void
    {
        $this->dropForeignKey('fk_catalog_products_material_template_id', '{{%catalog_products}}');
        $this->dropColumn('{{%catalog_products}}', 'supports');
        $this->dropColumn('{{%catalog_products}}', 'upholstery');
        $this->dropColumn('{{%catalog_products}}', 'filling');
        $this->dropColumn('{{%catalog_products}}', 'foundation');
        $this->dropColumn('{{%catalog_products}}', 'frame');
        $this->dropColumn('{{%catalog_products}}', 'material_template_id');
        $this->dropTable('{{%catalog_material_templates}}');
    }
}
