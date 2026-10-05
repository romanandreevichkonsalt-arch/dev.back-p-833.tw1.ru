<?php

use yii\db\Migration;

class m260924_160000_surface_materials_media_folder_label extends Migration
{
    public function safeUp(): void
    {
        $this->update('{{%media_folders}}', [
            'label' => 'Материалы',
            'sort_order' => 52,
        ], ['slug' => 'surface-materials']);
    }

    public function safeDown(): void
    {
        $this->update('{{%media_folders}}', [
            'label' => 'Дерево и металл',
            'sort_order' => 45,
        ], ['slug' => 'surface-materials']);
    }
}
