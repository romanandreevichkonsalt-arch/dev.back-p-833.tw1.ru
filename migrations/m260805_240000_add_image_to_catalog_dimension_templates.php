<?php

use yii\db\Migration;

class m260805_240000_add_image_to_catalog_dimension_templates extends Migration
{
    public function safeUp(): void
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
    }

    public function safeDown(): void
    {
        $this->dropForeignKey('fk_catalog_dimension_templates_image_id', '{{%catalog_dimension_templates}}');
        $this->dropColumn('{{%catalog_dimension_templates}}', 'image_id');
    }
}
