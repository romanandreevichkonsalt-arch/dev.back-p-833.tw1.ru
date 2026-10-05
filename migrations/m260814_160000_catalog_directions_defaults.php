<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * Базовые направления каталога: А+ и Линия 1.
 * Таблица уже называется catalog_directions (бывш. catalog_groups).
 */
class m260814_160000_catalog_directions_defaults extends Migration
{
    public function safeUp(): void
    {
        $now = date('Y-m-d H:i:s');

        foreach ([
            ['slug' => 'a-plus', 'label' => 'А+', 'sort_order' => 0],
            ['slug' => 'line-1', 'label' => 'Линия 1', 'sort_order' => 1],
        ] as $row) {
            $exists = (new Query())
                ->from('{{%catalog_directions}}')
                ->where(['slug' => $row['slug']])
                ->exists($this->db);

            if ($exists) {
                continue;
            }

            $this->insert('{{%catalog_directions}}', [
                'slug' => $row['slug'],
                'label' => $row['label'],
                'sort_order' => $row['sort_order'],
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function safeDown(): void
    {
        $this->delete('{{%catalog_directions}}', ['slug' => ['a-plus', 'line-1']]);
    }
}
