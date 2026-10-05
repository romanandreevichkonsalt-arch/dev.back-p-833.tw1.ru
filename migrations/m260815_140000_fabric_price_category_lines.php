<?php

use yii\db\Migration;

class m260815_140000_fabric_price_category_lines extends Migration
{
    public function safeUp(): void
    {
        if (!$this->columnExists('{{%catalog_price_categories}}', 'label_line1')) {
            $this->addColumn(
                '{{%catalog_price_categories}}',
                'label_line1',
                $this->string(255)->null()->after('price_max')
            );
        }
        if (!$this->columnExists('{{%catalog_price_categories}}', 'price_min_line1')) {
            $this->addColumn(
                '{{%catalog_price_categories}}',
                'price_min_line1',
                $this->integer()->null()->after('label_line1')
            );
        }
        if (!$this->columnExists('{{%catalog_price_categories}}', 'price_max_line1')) {
            $this->addColumn(
                '{{%catalog_price_categories}}',
                'price_max_line1',
                $this->integer()->null()->after('price_min_line1')
            );
        }

        $defaults = [
            1 => [
                'label' => '1 кат (до 700 руб)',
                'price_min' => null,
                'price_max' => 700,
                'label_line1' => '1 кат (до 400 руб)',
                'price_min_line1' => null,
                'price_max_line1' => 400,
            ],
            2 => [
                'label' => '2 кат (от 701 до 800 руб)',
                'price_min' => 701,
                'price_max' => 800,
                'label_line1' => '2 кат (от 401 до 500 руб)',
                'price_min_line1' => 401,
                'price_max_line1' => 500,
            ],
            3 => [
                'label' => '3 кат (от 801 до 900 руб)',
                'price_min' => 801,
                'price_max' => 900,
                'label_line1' => '3 кат (от 501 до 600 руб)',
                'price_min_line1' => 501,
                'price_max_line1' => 600,
            ],
            4 => [
                'label' => '4 кат (от 901 до 1000 руб)',
                'price_min' => 901,
                'price_max' => 1000,
                'label_line1' => '4 кат (от 601 до 700 руб)',
                'price_min_line1' => 601,
                'price_max_line1' => 700,
            ],
            5 => [
                'label' => '5 кат (от 1001 до 1100 руб)',
                'price_min' => 1001,
                'price_max' => 1100,
                'label_line1' => '5 кат (от 701 до 800 руб)',
                'price_min_line1' => 701,
                'price_max_line1' => 800,
            ],
            6 => [
                'label' => '6 кат (от 1101 до 1200 руб)',
                'price_min' => 1101,
                'price_max' => 1200,
                'label_line1' => '6 кат (от 801 до 900 руб)',
                'price_min_line1' => 801,
                'price_max_line1' => 900,
            ],
            7 => [
                'label' => '7 кат (от 1201 до 1300 руб)',
                'price_min' => 1201,
                'price_max' => 1300,
                'label_line1' => '7 кат (от 901 до 1000 руб)',
                'price_min_line1' => 901,
                'price_max_line1' => 1000,
            ],
            8 => [
                'label' => '8 кат (от 1301 до 1400 руб)',
                'price_min' => 1301,
                'price_max' => 1400,
                'label_line1' => '8 кат (от 1001 до 1100 руб)',
                'price_min_line1' => 1001,
                'price_max_line1' => 1100,
            ],
        ];

        foreach ($defaults as $number => $row) {
            $this->update(
                '{{%catalog_price_categories}}',
                $row,
                [
                    'and',
                    ['number' => $number],
                    ['or', ['label_line1' => null], ['label_line1' => '']],
                ]
            );
        }
    }

    public function safeDown(): void
    {
        foreach (['price_max_line1', 'price_min_line1', 'label_line1'] as $column) {
            if ($this->columnExists('{{%catalog_price_categories}}', $column)) {
                $this->dropColumn('{{%catalog_price_categories}}', $column);
            }
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        $schema = $this->db->schema->getTableSchema($table, true);

        return $schema !== null && isset($schema->columns[$column]);
    }
}
