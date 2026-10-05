<?php

use yii\db\Migration;

class m260928_210000_fabric_color_recommended_position extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn(
            '{{%catalog_fabric_collection_colors}}',
            'is_recommended_fabric',
            $this->boolean()->notNull()->defaultValue(false)->after('import_comment')
        );
        $this->addColumn(
            '{{%catalog_fabric_collection_colors}}',
            'position_number',
            $this->integer()->null()->after('is_recommended_fabric')
        );
    }

    public function safeDown(): void
    {
        $this->dropColumn('{{%catalog_fabric_collection_colors}}', 'position_number');
        $this->dropColumn('{{%catalog_fabric_collection_colors}}', 'is_recommended_fabric');
    }
}
