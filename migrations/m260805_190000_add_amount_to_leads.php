<?php

use yii\db\Migration;

class m260805_190000_add_amount_to_leads extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn(
            '{{%leads}}',
            'amount',
            $this->decimal(12, 2)->null()->after('city')
        );
    }

    public function safeDown(): void
    {
        $this->dropColumn('{{%leads}}', 'amount');
    }
}
