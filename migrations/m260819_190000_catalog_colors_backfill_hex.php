<?php

use app\helpers\CatalogFabricBaseColorPalette;
use yii\db\Migration;
use yii\db\Query;

class m260819_190000_catalog_colors_backfill_hex extends Migration
{
    public function safeUp(): void
    {
        $now = date('Y-m-d H:i:s');

        foreach ((new Query())->from('{{%catalog_colors}}')->all($this->db) as $row) {
            $hex = CatalogFabricBaseColorPalette::resolveHex(
                (string)($row['label'] ?? ''),
                (string)($row['slug'] ?? '')
            );
            if ($hex === null) {
                continue;
            }

            $currentHex = trim((string)($row['hex_color'] ?? ''));
            if ($currentHex !== '') {
                continue;
            }

            $this->update('{{%catalog_colors}}', [
                'hex_color' => $hex,
                'updated_at' => $now,
            ], ['id' => (int)$row['id']]);
        }
    }

    public function safeDown(): bool
    {
        return true;
    }
}
