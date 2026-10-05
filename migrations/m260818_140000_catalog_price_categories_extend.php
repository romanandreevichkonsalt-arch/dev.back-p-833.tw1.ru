<?php

use yii\db\Migration;
use yii\db\Query;

class m260818_140000_catalog_price_categories_extend extends Migration
{
    public function safeUp(): void
    {
        $now = date('Y-m-d H:i:s');
        $maxNumber = (int)(new Query())
            ->from('{{%catalog_price_categories}}')
            ->max('number');

        for ($number = max(9, $maxNumber + 1); $number <= 25; $number++) {
            $exists = (new Query())
                ->from('{{%catalog_price_categories}}')
                ->where(['number' => $number])
                ->exists($this->db);

            if ($exists) {
                continue;
            }

            $this->insert('{{%catalog_price_categories}}', [
                'number' => $number,
                'label' => 'Категория ' . $number,
                'sort_order' => $number - 1,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function safeDown(): void
    {
        $this->delete('{{%catalog_price_categories}}', ['and', ['>', 'number', 8], ['<=', 'number', 25]]);
    }
}
