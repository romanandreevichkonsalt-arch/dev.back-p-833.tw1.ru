<?php

use yii\db\Migration;

class m260807_192000_catalog_product_quantity extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn(
            '{{%catalog_products}}',
            'quantity',
            $this->integer()->notNull()->defaultValue(0)->after('price_display')->comment('Остаток на складе')
        );
    }

    public function safeDown(): void
    {
        $this->dropColumn('{{%catalog_products}}', 'quantity');
    }
}
