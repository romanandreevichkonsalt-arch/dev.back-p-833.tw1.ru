<?php

use yii\db\Migration;

class m260905_160000_order_payment_and_documents extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn('{{%orders}}', 'payment_method', $this->string(32)->null()->after('comment'));
        $this->addColumn(
            '{{%orders}}',
            'cashless_surcharge_amount',
            $this->decimal(12, 2)->notNull()->defaultValue(0)->after('payment_method')
        );

        $this->createTable('{{%order_documents}}', [
            'id' => $this->primaryKey(),
            'order_id' => $this->integer()->notNull(),
            'label' => $this->string(255)->notNull(),
            'stored_path' => $this->string(512)->notNull(),
            'original_name' => $this->string(255)->null(),
            'sort_order' => $this->integer()->notNull()->defaultValue(0),
            'created_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex('idx_order_documents_order_id', '{{%order_documents}}', 'order_id');
        $this->addForeignKey(
            'fk_order_documents_order_id',
            '{{%order_documents}}',
            'order_id',
            '{{%orders}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
    }

    public function safeDown(): bool
    {
        $this->dropForeignKey('fk_order_documents_order_id', '{{%order_documents}}');
        $this->dropTable('{{%order_documents}}');
        $this->dropColumn('{{%orders}}', 'cashless_surcharge_amount');
        $this->dropColumn('{{%orders}}', 'payment_method');

        return true;
    }
}
