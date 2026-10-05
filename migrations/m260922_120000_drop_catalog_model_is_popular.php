<?php

use yii\db\Migration;

class m260922_120000_drop_catalog_model_is_popular extends Migration
{
    public function safeUp(): void
    {
        $schema = $this->db->schema->getTableSchema('{{%catalog_models}}', true);
        if ($schema !== null && isset($schema->columns['is_popular'])) {
            $this->dropColumn('{{%catalog_models}}', 'is_popular');
        }
    }

    public function safeDown(): void
    {
        $schema = $this->db->schema->getTableSchema('{{%catalog_models}}', true);
        if ($schema !== null && !isset($schema->columns['is_popular'])) {
            $this->addColumn(
                '{{%catalog_models}}',
                'is_popular',
                $this->boolean()->notNull()->defaultValue(false)->after('is_active')
            );
        }
    }
}
