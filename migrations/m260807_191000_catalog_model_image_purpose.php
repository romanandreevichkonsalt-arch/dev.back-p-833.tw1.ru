<?php

use yii\db\Migration;

class m260807_191000_catalog_model_image_purpose extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn(
            '{{%catalog_model_images}}',
            'purpose',
            $this->string(16)->notNull()->defaultValue('angle')->after('media_file_id')
        );
        $this->createIndex('idx_catalog_model_images_purpose', '{{%catalog_model_images}}', ['model_id', 'purpose']);
    }

    public function safeDown(): void
    {
        $this->dropIndex('idx_catalog_model_images_purpose', '{{%catalog_model_images}}');
        $this->dropColumn('{{%catalog_model_images}}', 'purpose');
    }
}
