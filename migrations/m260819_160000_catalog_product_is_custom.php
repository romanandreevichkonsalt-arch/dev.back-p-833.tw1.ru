<?php

use yii\db\Migration;

class m260819_160000_catalog_product_is_custom extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn(
            '{{%catalog_products}}',
            'is_custom',
            $this->boolean()->notNull()->defaultValue(false)->after('is_search_recommended')
                ->comment('SKU «кастом» модели без выбранного цвета ткани')
        );

        $this->update(
            '{{%catalog_products}}',
            ['is_custom' => true],
            'model_id IS NOT NULL AND fabric_color_id IS NULL'
        );
    }

    public function safeDown(): bool
    {
        $this->dropColumn('{{%catalog_products}}', 'is_custom');

        return true;
    }
}
