<?php

use yii\db\Migration;

class m260908_150000_search_recommended_products extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%search_recommended_products}}', [
            'id' => $this->primaryKey(),
            'scope_type' => $this->string(32)->notNull(),
            'scope_id' => $this->integer()->notNull()->defaultValue(0),
            'slot' => $this->smallInteger()->notNull(),
            'catalog_product_id' => $this->integer()->notNull(),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);

        $this->createIndex(
            'idx_search_recommended_scope_slot',
            '{{%search_recommended_products}}',
            ['scope_type', 'scope_id', 'slot'],
            true
        );
        $this->createIndex(
            'idx_search_recommended_product',
            '{{%search_recommended_products}}',
            'catalog_product_id'
        );

        $this->addForeignKey(
            'fk_search_recommended_product',
            '{{%search_recommended_products}}',
            'catalog_product_id',
            '{{%catalog_products}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $now = date('Y-m-d H:i:s');
        $rows = (new \yii\db\Query())
            ->from('{{%catalog_products}}')
            ->where(['is_search_recommended' => true])
            ->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC])
            ->limit(3)
            ->all($this->db);

        foreach ($rows as $slot => $row) {
            $this->insert('{{%search_recommended_products}}', [
                'scope_type' => 'main',
                'scope_id' => 0,
                'slot' => (int)$slot,
                'catalog_product_id' => (int)$row['id'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $this->dropIndex('idx_catalog_products_is_search_recommended', '{{%catalog_products}}');
        $this->dropColumn('{{%catalog_products}}', 'is_search_recommended');
    }

    public function safeDown(): void
    {
        $this->addColumn(
            '{{%catalog_products}}',
            'is_search_recommended',
            $this->boolean()->notNull()->defaultValue(false)->after('is_active')
        );
        $this->createIndex(
            'idx_catalog_products_is_search_recommended',
            '{{%catalog_products}}',
            'is_search_recommended'
        );

        $rows = (new \yii\db\Query())
            ->from('{{%search_recommended_products}}')
            ->where(['scope_type' => 'main'])
            ->all($this->db);

        foreach ($rows as $row) {
            $this->update(
                '{{%catalog_products}}',
                ['is_search_recommended' => true],
                ['id' => (int)$row['catalog_product_id']]
            );
        }

        $this->dropForeignKey('fk_search_recommended_product', '{{%search_recommended_products}}');
        $this->dropTable('{{%search_recommended_products}}');
    }
}
