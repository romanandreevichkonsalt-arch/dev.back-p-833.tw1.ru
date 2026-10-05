<?php

use app\services\catalog\CatalogListingValueParser;
use yii\db\Migration;
use yii\db\Query;

class m260829_120000_catalog_product_listing_fields extends Migration
{
    public function safeUp(): void
    {
        foreach (['{{%catalog_models}}', '{{%catalog_products}}'] as $table) {
            $this->addColumn($table, 'width_mm', $this->integer()->null()->after('overall_size'));
            $this->addColumn($table, 'height_mm', $this->integer()->null()->after('width_mm'));
            $this->addColumn($table, 'depth_mm', $this->integer()->null()->after('height_mm'));
        }

        $this->addColumn('{{%catalog_products}}', 'price_amount', $this->integer()->null()->after('price_display'));

        $this->backfillModels();
        $this->backfillProducts();
    }

    public function safeDown(): void
    {
        $this->dropColumn('{{%catalog_products}}', 'price_amount');

        foreach (['{{%catalog_models}}', '{{%catalog_products}}'] as $table) {
            $this->dropColumn($table, 'depth_mm');
            $this->dropColumn($table, 'height_mm');
            $this->dropColumn($table, 'width_mm');
        }
    }

    private function backfillModels(): void
    {
        $rows = (new Query())
            ->from('{{%catalog_models}}')
            ->select(['id', 'overall_size'])
            ->all();

        foreach ($rows as $row) {
            $dimensions = CatalogListingValueParser::parseOverallSizeMm($row['overall_size'] ?? null);
            $this->update('{{%catalog_models}}', [
                'width_mm' => $dimensions['width'] ?? null,
                'height_mm' => $dimensions['height'] ?? null,
                'depth_mm' => $dimensions['depth'] ?? null,
            ], ['id' => (int)$row['id']]);
        }
    }

    private function backfillProducts(): void
    {
        $rows = (new Query())
            ->from('{{%catalog_products}}')
            ->select(['id', 'price_display', 'overall_size'])
            ->all();

        foreach ($rows as $row) {
            $dimensions = CatalogListingValueParser::parseOverallSizeMm($row['overall_size'] ?? null);
            $this->update('{{%catalog_products}}', [
                'price_amount' => CatalogListingValueParser::parsePriceAmount($row['price_display'] ?? null),
                'width_mm' => $dimensions['width'] ?? null,
                'height_mm' => $dimensions['height'] ?? null,
                'depth_mm' => $dimensions['depth'] ?? null,
            ], ['id' => (int)$row['id']]);
        }
    }
}
