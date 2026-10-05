<?php

use yii\db\Migration;

class m260917_190000_catalog_model_3d_fields extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn(
            '{{%catalog_models}}',
            'polygons_3d',
            $this->string(64)->null()->after('fitting_room_url')
        );
        $this->addColumn(
            '{{%catalog_models}}',
            'file_3d_url',
            $this->string(512)->null()->after('polygons_3d')
        );
        $this->addColumn(
            '{{%catalog_models}}',
            'file_3d_id',
            $this->integer()->null()->after('file_3d_url')
        );

        $this->addForeignKey(
            'fk_catalog_models_file_3d_id',
            '{{%catalog_models}}',
            'file_3d_id',
            '{{%media_files}}',
            'id',
            'SET NULL',
            'CASCADE'
        );

        $exists = (new \yii\db\Query())
            ->from('{{%media_folders}}')
            ->where(['slug' => 'models-3d'])
            ->exists($this->db);

        if (!$exists) {
            $this->insert('{{%media_folders}}', [
                'slug' => 'models-3d',
                'label' => '3D-модели',
                'sort_order' => 46,
            ]);
        }
    }

    public function safeDown(): void
    {
        $this->dropForeignKey('fk_catalog_models_file_3d_id', '{{%catalog_models}}');
        $this->dropColumn('{{%catalog_models}}', 'file_3d_id');
        $this->dropColumn('{{%catalog_models}}', 'file_3d_url');
        $this->dropColumn('{{%catalog_models}}', 'polygons_3d');
        $this->delete('{{%media_folders}}', ['slug' => 'models-3d']);
    }
}
