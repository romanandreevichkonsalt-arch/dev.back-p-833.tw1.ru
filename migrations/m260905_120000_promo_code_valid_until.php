<?php

use yii\db\Migration;

class m260905_120000_promo_code_valid_until extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn(
            '{{%promo_code_templates}}',
            'valid_until',
            $this->date()->null()->after('default_valid_days')
        );
    }

    public function safeDown(): void
    {
        $this->dropColumn('{{%promo_code_templates}}', 'valid_until');
    }
}
