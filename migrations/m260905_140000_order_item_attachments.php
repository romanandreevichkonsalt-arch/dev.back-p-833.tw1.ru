<?php

use yii\db\Migration;

class m260905_140000_order_item_attachments extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn('{{%order_items}}', 'attachment_path', $this->string(512)->null()->after('comment'));
        $this->addColumn('{{%order_items}}', 'attachment_original_name', $this->string(255)->null()->after('attachment_path'));
    }

    public function safeDown(): bool
    {
        $this->dropColumn('{{%order_items}}', 'attachment_original_name');
        $this->dropColumn('{{%order_items}}', 'attachment_path');

        return true;
    }
}
