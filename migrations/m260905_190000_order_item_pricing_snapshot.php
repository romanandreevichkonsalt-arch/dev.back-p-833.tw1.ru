<?php

use yii\db\Migration;

class m260905_190000_order_item_pricing_snapshot extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn(
            '{{%order_items}}',
            'promo_discount_amount',
            $this->decimal(12, 2)->notNull()->defaultValue(0)->after('line_total')
        );
        $this->addColumn(
            '{{%order_items}}',
            'cashback_used_amount',
            $this->decimal(12, 2)->notNull()->defaultValue(0)->after('promo_discount_amount')
        );
        $this->addColumn(
            '{{%order_items}}',
            'paid_line_total',
            $this->decimal(12, 2)->notNull()->defaultValue(0)->after('cashback_used_amount')
        );

        $this->update('{{%order_items}}', [
            'paid_line_total' => new \yii\db\Expression('line_total'),
        ]);
    }

    public function safeDown(): bool
    {
        $this->dropColumn('{{%order_items}}', 'paid_line_total');
        $this->dropColumn('{{%order_items}}', 'cashback_used_amount');
        $this->dropColumn('{{%order_items}}', 'promo_discount_amount');

        return true;
    }
}
