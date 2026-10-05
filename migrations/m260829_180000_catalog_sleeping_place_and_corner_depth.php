<?php

use yii\db\Migration;

class m260829_180000_catalog_sleeping_place_and_corner_depth extends Migration
{
    public function safeUp(): void
    {
        foreach (['{{%catalog_models}}', '{{%catalog_products}}'] as $table) {
            $this->addColumn($table, 'has_sleeping_place', $this->boolean()->notNull()->defaultValue(false)->after('leg_height'));
            $this->addColumn($table, 'corner_depth_mm', $this->integer()->null()->after('depth_mm'));
        }
    }

    public function safeDown(): void
    {
        foreach (['{{%catalog_products}}', '{{%catalog_models}}'] as $table) {
            $this->dropColumn($table, 'corner_depth_mm');
            $this->dropColumn($table, 'has_sleeping_place');
        }
    }
}
