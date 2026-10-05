<?php

use yii\db\Migration;

class m260805_230000_add_subtitle_description_to_catalog_products extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn('{{%catalog_products}}', 'subtitle', $this->string(255)->null()->after('title'));
        $this->addColumn('{{%catalog_products}}', 'description', $this->text()->null()->after('subtitle'));
    }

    public function safeDown(): void
    {
        $this->dropColumn('{{%catalog_products}}', 'description');
        $this->dropColumn('{{%catalog_products}}', 'subtitle');
    }
}
