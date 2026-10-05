<?php

use yii\db\Migration;

class m260812_220000_catalog_price_categories extends Migration
{
    public function safeUp(): void
    {
        $now = date('Y-m-d H:i:s');

        $this->createTable('{{%catalog_price_categories}}', [
            'id' => $this->primaryKey(),
            'number' => $this->tinyInteger()->notNull()->comment('Номер категории для отображения и API'),
            'label' => $this->string(255)->notNull(),
            'sort_order' => $this->integer()->notNull()->defaultValue(0),
            'is_active' => $this->boolean()->notNull()->defaultValue(true),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex('ux_catalog_price_categories_number', '{{%catalog_price_categories}}', 'number', true);

        for ($number = 1; $number <= 8; $number++) {
            $this->insert('{{%catalog_price_categories}}', [
                'number' => $number,
                'label' => 'Категория ' . $number,
                'sort_order' => $number - 1,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $this->addColumn(
            '{{%catalog_model_prices}}',
            'price_category_id',
            $this->integer()->null()->after('model_id')
        );

        $this->execute(
            'UPDATE {{%catalog_model_prices}} mp
             INNER JOIN {{%catalog_price_categories}} pc ON pc.number = mp.category
             SET mp.price_category_id = pc.id'
        );

        $this->createIndex(
            'idx_catalog_model_prices_model_id',
            '{{%catalog_model_prices}}',
            'model_id'
        );
        $this->dropIndex('ux_catalog_model_prices_model_category', '{{%catalog_model_prices}}');
        $this->dropColumn('{{%catalog_model_prices}}', 'category');

        $this->alterColumn('{{%catalog_model_prices}}', 'price_category_id', $this->integer()->notNull());
        $this->createIndex(
            'ux_catalog_model_prices_model_price_category',
            '{{%catalog_model_prices}}',
            ['model_id', 'price_category_id'],
            true
        );
        $this->addForeignKey(
            'fk_catalog_model_prices_price_category_id',
            '{{%catalog_model_prices}}',
            'price_category_id',
            '{{%catalog_price_categories}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->addColumn(
            '{{%catalog_fabric_collections}}',
            'price_category_id',
            $this->integer()->null()->after('meter_price_display')
        );

        $this->execute(
            'UPDATE {{%catalog_fabric_collections}} fc
             INNER JOIN {{%catalog_price_categories}} pc ON pc.number = fc.price_category
             SET fc.price_category_id = pc.id
             WHERE fc.price_category IS NOT NULL'
        );

        $this->dropColumn('{{%catalog_fabric_collections}}', 'price_category');

        $this->addForeignKey(
            'fk_catalog_fabric_collections_price_category_id',
            '{{%catalog_fabric_collections}}',
            'price_category_id',
            '{{%catalog_price_categories}}',
            'id',
            'SET NULL',
            'CASCADE'
        );
    }

    public function safeDown(): void
    {
        $this->addColumn(
            '{{%catalog_fabric_collections}}',
            'price_category',
            $this->tinyInteger()->null()->after('meter_price_display')
        );

        $this->execute(
            'UPDATE {{%catalog_fabric_collections}} fc
             INNER JOIN {{%catalog_price_categories}} pc ON pc.id = fc.price_category_id
             SET fc.price_category = pc.number
             WHERE fc.price_category_id IS NOT NULL'
        );

        $this->dropForeignKey('fk_catalog_fabric_collections_price_category_id', '{{%catalog_fabric_collections}}');
        $this->dropColumn('{{%catalog_fabric_collections}}', 'price_category_id');

        $this->dropForeignKey('fk_catalog_model_prices_price_category_id', '{{%catalog_model_prices}}');
        $this->dropIndex('ux_catalog_model_prices_model_price_category', '{{%catalog_model_prices}}');

        $this->addColumn(
            '{{%catalog_model_prices}}',
            'category',
            $this->tinyInteger()->null()->after('model_id')
        );

        $this->execute(
            'UPDATE {{%catalog_model_prices}} mp
             INNER JOIN {{%catalog_price_categories}} pc ON pc.id = mp.price_category_id
             SET mp.category = pc.number'
        );

        $this->dropColumn('{{%catalog_model_prices}}', 'price_category_id');
        $this->dropIndex('idx_catalog_model_prices_model_id', '{{%catalog_model_prices}}');

        $this->alterColumn('{{%catalog_model_prices}}', 'category', $this->tinyInteger()->notNull());
        $this->createIndex(
            'ux_catalog_model_prices_model_category',
            '{{%catalog_model_prices}}',
            ['model_id', 'category'],
            true
        );

        $this->dropTable('{{%catalog_price_categories}}');
    }
}
