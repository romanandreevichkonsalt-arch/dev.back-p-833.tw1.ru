<?php

use yii\db\Migration;

class m260908_120000_catalog_model_foldable_and_sleeping_size extends Migration
{
    public function safeUp(): void
    {
        foreach (['{{%catalog_models}}', '{{%catalog_products}}'] as $table) {
            $this->addColumn($table, 'is_foldable', $this->boolean()->notNull()->defaultValue(false)->after('has_sleeping_place'));
            $this->addColumn($table, 'sleeping_place_width_mm', $this->integer()->null()->after('is_foldable'));
            $this->addColumn($table, 'sleeping_place_depth_mm', $this->integer()->null()->after('sleeping_place_width_mm'));
        }
    }

    public function safeDown(): void
    {
        foreach (['{{%catalog_products}}', '{{%catalog_models}}'] as $table) {
            $this->dropColumn($table, 'sleeping_place_depth_mm');
            $this->dropColumn($table, 'sleeping_place_width_mm');
            $this->dropColumn($table, 'is_foldable');
        }
    }
}
