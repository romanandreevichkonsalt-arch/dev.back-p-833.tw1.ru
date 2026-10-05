<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * Перевод коллекций на направления А+ / Линия 1, деактивация старых «групп» (Диваны и т.д.).
 */
class m260814_170000_catalog_migrate_collections_to_directions extends Migration
{
    public function safeUp(): void
    {
        $aPlusId = (new Query())
            ->from('{{%catalog_directions}}')
            ->select('id')
            ->where(['slug' => 'a-plus'])
            ->scalar();
        $line1Id = (new Query())
            ->from('{{%catalog_directions}}')
            ->select('id')
            ->where(['slug' => 'line-1'])
            ->scalar();

        if ($aPlusId) {
            $this->update(
                '{{%catalog_collections}}',
                ['direction_id' => (int)$aPlusId],
                ['slug' => 'a-plus']
            );
        }

        if ($line1Id) {
            $this->update(
                '{{%catalog_collections}}',
                ['direction_id' => (int)$line1Id],
                ['slug' => ['test-col', 'test-nova', 'test-luna']]
            );
        }

        $legacySlugs = ['sofas', 'armchairs', 'combinations', 'accessories'];
        $this->update(
            '{{%catalog_directions}}',
            ['is_active' => false],
            ['slug' => $legacySlugs]
        );
    }

    public function safeDown(): void
    {
        $sofasId = (new Query())
            ->from('{{%catalog_directions}}')
            ->select('id')
            ->where(['slug' => 'sofas'])
            ->scalar();

        if ($sofasId) {
            $this->update(
                '{{%catalog_collections}}',
                ['direction_id' => (int)$sofasId],
                ['slug' => ['a-plus', 'test-col', 'test-nova', 'test-luna']]
            );
            $this->update('{{%catalog_directions}}', ['is_active' => true], ['slug' => 'sofas']);
        }
    }
}
