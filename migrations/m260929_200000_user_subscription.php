<?php

use yii\db\Migration;

class m260929_200000_user_subscription extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn(
            '{{%users}}',
            'subscription',
            $this->boolean()->notNull()->defaultValue(false)->after('is_blocked')
        );
    }

    public function safeDown(): void
    {
        $this->dropColumn('{{%users}}', 'subscription');
    }
}
