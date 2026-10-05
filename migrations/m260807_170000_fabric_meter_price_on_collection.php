<?php

use yii\db\Migration;

class m260807_170000_fabric_meter_price_on_collection extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn(
            '{{%catalog_fabric_collections}}',
            'meter_price_display',
            $this->string(64)->null()->after('description')->comment('Стоимость погонного метра')
        );
        $this->addColumn(
            '{{%catalog_fabric_collections}}',
            'price_category',
            $this->tinyInteger()->null()->after('meter_price_display')->comment('Ценовая категория 1–8, позже — авто из стоимости')
        );

        if ($this->db->getTableSchema('{{%catalog_fabric_colors}}')->getColumn('meter_price_display') !== null) {
            $collections = (new \yii\db\Query())
                ->select(['id'])
                ->from('{{%catalog_fabric_collections}}')
                ->column();

            foreach ($collections as $collectionId) {
                $meterPrice = (new \yii\db\Query())
                    ->select('meter_price_display')
                    ->from('{{%catalog_fabric_colors}}')
                    ->where(['fabric_collection_id' => $collectionId])
                    ->andWhere(['not', ['meter_price_display' => null]])
                    ->andWhere(['<>', 'meter_price_display', ''])
                    ->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC])
                    ->scalar();

                if ($meterPrice !== false && $meterPrice !== null && $meterPrice !== '') {
                    $this->update('{{%catalog_fabric_collections}}', [
                        'meter_price_display' => $meterPrice,
                    ], ['id' => $collectionId]);
                }
            }

            $this->dropColumn('{{%catalog_fabric_colors}}', 'meter_price_display');
        }
    }

    public function safeDown(): void
    {
        if ($this->db->getTableSchema('{{%catalog_fabric_colors}}')->getColumn('meter_price_display') === null) {
            $this->addColumn(
                '{{%catalog_fabric_colors}}',
                'meter_price_display',
                $this->string(64)->null()->after('category')
            );
        }

        $this->dropColumn('{{%catalog_fabric_collections}}', 'price_category');
        $this->dropColumn('{{%catalog_fabric_collections}}', 'meter_price_display');
    }
}
