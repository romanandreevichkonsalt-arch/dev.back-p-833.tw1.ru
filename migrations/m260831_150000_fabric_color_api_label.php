<?php

use app\models\CatalogFabricColor;
use yii\db\Migration;
use yii\db\Query;

class m260831_150000_fabric_color_api_label extends Migration
{
    public function safeUp(): void
    {
        if ($this->db->getTableSchema('{{%catalog_fabric_collection_colors}}')->getColumn('api_label') === null) {
            $this->addColumn(
                '{{%catalog_fabric_collection_colors}}',
                'api_label',
                $this->string(255)->null()->after('design_code')
            );
        }

        $rows = (new Query())
            ->select([
                'l.id',
                'l.design_code',
                'fc.name AS collection_name',
                'cc.label AS color_label',
            ])
            ->from(['l' => '{{%catalog_fabric_collection_colors}}'])
            ->innerJoin(['fc' => '{{%catalog_fabric_collections}}'], 'fc.id = l.fabric_collection_id')
            ->leftJoin(['cc' => '{{%catalog_colors}}'], 'cc.id = l.color_id')
            ->all($this->db);

        foreach ($rows as $row) {
            $this->update('{{%catalog_fabric_collection_colors}}', [
                'api_label' => CatalogFabricColor::buildApiLabel(
                    (string)($row['collection_name'] ?? ''),
                    (string)($row['design_code'] ?? ''),
                    isset($row['color_label']) ? (string)$row['color_label'] : null
                ),
            ], ['id' => (int)$row['id']]);
        }
    }

    public function safeDown(): void
    {
        if ($this->db->getTableSchema('{{%catalog_fabric_collection_colors}}')->getColumn('api_label') !== null) {
            $this->dropColumn('{{%catalog_fabric_collection_colors}}', 'api_label');
        }
    }
}
