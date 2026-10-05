<?php

use yii\db\Migration;

class m260807_160000_fabric_color_meter_price extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn(
            '{{%catalog_fabric_colors}}',
            'meter_price_display',
            $this->string(64)->null()->after('category')->comment('Стоимость погонного метра (отображение)')
        );
    }

    public function safeDown(): void
    {
        $this->dropColumn('{{%catalog_fabric_colors}}', 'meter_price_display');
    }
}
