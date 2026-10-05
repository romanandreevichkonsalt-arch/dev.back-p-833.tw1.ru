<?php

use yii\db\Migration;

class m260922_151000_drop_promotion_popup_dismiss_until_end_of_day extends Migration
{
    public function safeUp(): void
    {
        $schema = $this->db->schema->getTableSchema('{{%promotion_popup}}', true);
        if ($schema !== null && isset($schema->columns['dismiss_until_end_of_day'])) {
            $this->dropColumn('{{%promotion_popup}}', 'dismiss_until_end_of_day');
        }
    }

    public function safeDown(): void
    {
        $schema = $this->db->schema->getTableSchema('{{%promotion_popup}}', true);
        if ($schema !== null && !isset($schema->columns['dismiss_until_end_of_day'])) {
            $this->addColumn(
                '{{%promotion_popup}}',
                'dismiss_until_end_of_day',
                $this->boolean()->notNull()->defaultValue(true)
            );
        }
    }
}
