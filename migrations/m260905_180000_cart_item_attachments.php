<?php

use yii\db\Migration;

class m260905_180000_cart_item_attachments extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn('{{%cart_items}}', 'attachment_path', $this->string(512)->null()->after('comment'));
        $this->addColumn('{{%cart_items}}', 'attachment_original_name', $this->string(255)->null()->after('attachment_path'));
    }

    public function safeDown(): bool
    {
        $this->dropColumn('{{%cart_items}}', 'attachment_original_name');
        $this->dropColumn('{{%cart_items}}', 'attachment_path');

        return true;
    }
}
