<?php

use yii\db\Migration;

class m260829_190000_catalog_is_popular extends Migration
{
    public function safeUp(): void
    {
        foreach (['{{%catalog_models}}', '{{%catalog_products}}'] as $table) {
            $this->addColumn($table, 'is_popular', $this->boolean()->notNull()->defaultValue(false)->after('is_active'));
        }
    }

    public function safeDown(): void
    {
        foreach (['{{%catalog_products}}', '{{%catalog_models}}'] as $table) {
            $this->dropColumn($table, 'is_popular');
        }
    }
}
