<?php

use yii\db\Migration;

class m260922_153000_drop_catalog_promotion_priority extends Migration
{
    public function safeUp(): void
    {
        $schema = $this->db->schema->getTableSchema('{{%catalog_promotions}}', true);
        if ($schema !== null && isset($schema->columns['priority'])) {
            $this->dropColumn('{{%catalog_promotions}}', 'priority');
        }
    }

    public function safeDown(): void
    {
        $schema = $this->db->schema->getTableSchema('{{%catalog_promotions}}', true);
        if ($schema !== null && !isset($schema->columns['priority'])) {
            $this->addColumn(
                '{{%catalog_promotions}}',
                'priority',
                $this->integer()->notNull()->defaultValue(0)
            );
        }
    }
}
