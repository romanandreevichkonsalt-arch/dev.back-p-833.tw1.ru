<?php

use yii\db\Migration;

class m260905_210000_dealer_profile_inn_nullable extends Migration
{
    public function safeUp(): void
    {
        $this->alterColumn('{{%dealer_profiles}}', 'inn', $this->string(12)->null());
        $this->update('{{%dealer_profiles}}', ['inn' => null], ['inn' => '']);
    }

    public function safeDown(): void
    {
        $this->update('{{%dealer_profiles}}', ['inn' => '0000000000'], ['inn' => null]);
        $this->alterColumn('{{%dealer_profiles}}', 'inn', $this->string(12)->notNull());
    }
}
