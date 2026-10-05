<?php

use yii\db\Migration;

class m260814_190000_catalog_model_price_categories extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%catalog_model_price_categories}}', [
            'model_id' => $this->integer()->notNull(),
            'price_category_id' => $this->integer()->notNull(),
            'sort_order' => $this->integer()->notNull()->defaultValue(0),
            'created_at' => $this->dateTime()->notNull(),
        ]);

        $this->addPrimaryKey(
            'pk_catalog_model_price_categories',
            '{{%catalog_model_price_categories}}',
            ['model_id', 'price_category_id']
        );

        $this->createIndex(
            'idx_catalog_model_price_categories_model_sort',
            '{{%catalog_model_price_categories}}',
            ['model_id', 'sort_order']
        );

        $this->addForeignKey(
            'fk_catalog_model_price_categories_model_id',
            '{{%catalog_model_price_categories}}',
            'model_id',
            '{{%catalog_models}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->addForeignKey(
            'fk_catalog_model_price_categories_price_category_id',
            '{{%catalog_model_price_categories}}',
            'price_category_id',
            '{{%catalog_price_categories}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->backfillModelPriceCategories();
    }

    public function safeDown(): void
    {
        $this->dropForeignKey('fk_catalog_model_price_categories_price_category_id', '{{%catalog_model_price_categories}}');
        $this->dropForeignKey('fk_catalog_model_price_categories_model_id', '{{%catalog_model_price_categories}}');
        $this->dropTable('{{%catalog_model_price_categories}}');
    }

    private function backfillModelPriceCategories(): void
    {
        $defaultCategoryIds = $this->db->createCommand(
            'SELECT id FROM {{%catalog_price_categories}}
             WHERE is_active = 1
             ORDER BY sort_order ASC, number ASC, id ASC
             LIMIT 8'
        )->queryColumn();

        $defaultCategoryIds = array_map('intval', $defaultCategoryIds);
        $modelIds = $this->db->createCommand('SELECT id FROM {{%catalog_models}}')->queryColumn();
        $now = date('Y-m-d H:i:s');

        foreach ($modelIds as $modelId) {
            $modelId = (int)$modelId;
            $pricedCategoryIds = $this->db->createCommand(
                'SELECT DISTINCT price_category_id
                 FROM {{%catalog_model_prices}}
                 WHERE model_id = :model_id',
                ['model_id' => $modelId]
            )->queryColumn();

            $categoryIds = $defaultCategoryIds;
            foreach (array_map('intval', $pricedCategoryIds) as $categoryId) {
                if ($categoryId > 0 && !in_array($categoryId, $categoryIds, true)) {
                    $categoryIds[] = $categoryId;
                }
            }

            if ($categoryIds === []) {
                continue;
            }

            $orderedIds = $this->orderCategoryIds($categoryIds);
            foreach ($orderedIds as $sortOrder => $categoryId) {
                $this->insert('{{%catalog_model_price_categories}}', [
                    'model_id' => $modelId,
                    'price_category_id' => $categoryId,
                    'sort_order' => $sortOrder,
                    'created_at' => $now,
                ]);
            }
        }
    }

    /**
     * @param int[] $categoryIds
     * @return int[]
     */
    private function orderCategoryIds(array $categoryIds): array
    {
        if ($categoryIds === []) {
            return [];
        }

        $rows = $this->db->createCommand(
            'SELECT id FROM {{%catalog_price_categories}}
             WHERE id IN (' . implode(',', array_map('intval', $categoryIds)) . ')
             ORDER BY sort_order ASC, number ASC, id ASC'
        )->queryColumn();

        return array_map('intval', $rows);
    }
}
