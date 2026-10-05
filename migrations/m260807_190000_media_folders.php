<?php

use yii\db\Migration;

class m260807_190000_media_folders extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%media_folders}}', [
            'id' => $this->primaryKey(),
            'slug' => $this->string(32)->notNull()->unique(),
            'label' => $this->string(64)->notNull(),
            'sort_order' => $this->integer()->notNull()->defaultValue(0),
        ]);

        $folders = [
            ['angles', 'Ракурсы', 10],
            ['interior', 'Интерьер', 20],
            ['tech', 'Тех. фото', 30],
            ['video', 'Видео', 40],
            ['fabrics', 'Ткани', 50],
            ['banners', 'Баннеры', 60],
            ['general', 'Общие', 70],
        ];

        foreach ($folders as [$slug, $label, $sort]) {
            $this->insert('{{%media_folders}}', [
                'slug' => $slug,
                'label' => $label,
                'sort_order' => $sort,
            ]);
        }

        $this->addColumn('{{%media_files}}', 'folder_id', $this->integer()->null()->after('kind'));
        $this->createIndex('idx_media_files_folder_id', '{{%media_files}}', 'folder_id');
        $this->addForeignKey(
            'fk_media_files_folder_id',
            '{{%media_files}}',
            'folder_id',
            '{{%media_folders}}',
            'id',
            'SET NULL',
            'CASCADE'
        );

        $generalId = (new \yii\db\Query())
            ->select('id')
            ->from('{{%media_folders}}')
            ->where(['slug' => 'general'])
            ->scalar();

        if ($generalId) {
            $this->update('{{%media_files}}', ['folder_id' => $generalId], ['folder_id' => null]);
        }
    }

    public function safeDown(): void
    {
        $this->dropForeignKey('fk_media_files_folder_id', '{{%media_files}}');
        $this->dropColumn('{{%media_files}}', 'folder_id');
        $this->dropTable('{{%media_folders}}');
    }
}
