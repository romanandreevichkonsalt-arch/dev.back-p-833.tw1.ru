<?php

use yii\db\Migration;

class m260926_120000_order_item_discount_snapshot extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn(
            '{{%order_items}}',
            'has_catalog_promotion',
            $this->boolean()->notNull()->defaultValue(false)->after('dealer_unit_price')
        );
        $this->addColumn(
            '{{%order_items}}',
            'catalog_promotion_discount',
            $this->decimal(12, 2)->notNull()->defaultValue(0)->after('has_catalog_promotion')
        );
        $this->addColumn(
            '{{%order_items}}',
            'catalog_promotion_snapshot',
            $this->text()->null()->after('catalog_promotion_discount')
        );
        $this->addColumn(
            '{{%order_items}}',
            'dealer_discount_percent',
            $this->integer()->null()->after('catalog_promotion_snapshot')
        );
    }

    public function safeDown(): bool
    {
        $this->dropColumn('{{%order_items}}', 'dealer_discount_percent');
        $this->dropColumn('{{%order_items}}', 'catalog_promotion_snapshot');
        $this->dropColumn('{{%order_items}}', 'catalog_promotion_discount');
        $this->dropColumn('{{%order_items}}', 'has_catalog_promotion');

        return true;
    }
}
