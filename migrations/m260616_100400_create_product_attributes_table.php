<?php

use yii\db\Migration;

class m260616_100400_create_product_attributes_table extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%product_attributes}}', [
            'id' => $this->primaryKey(),
            'product_id' => $this->integer()->notNull(),
            'attribute_code' => $this->string(64)->notNull(),
            'value_string' => $this->string(255)->null(),
            'value_number' => $this->decimal(12, 4)->null(),
            'value_bool' => $this->boolean()->null(),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);

        $this->createIndex('idx_product_attributes_product_id', '{{%product_attributes}}', 'product_id');
        $this->createIndex('idx_product_attributes_code', '{{%product_attributes}}', 'attribute_code');
        $this->createIndex(
            'idx_product_attributes_code_value_string',
            '{{%product_attributes}}',
            ['attribute_code', 'value_string']
        );
        $this->createIndex(
            'idx_product_attributes_code_value_number',
            '{{%product_attributes}}',
            ['attribute_code', 'value_number']
        );
        $this->createIndex(
            'idx_product_attributes_code_value_bool',
            '{{%product_attributes}}',
            ['attribute_code', 'value_bool']
        );
        $this->createIndex(
            'uq_product_attributes_product_code',
            '{{%product_attributes}}',
            ['product_id', 'attribute_code'],
            true
        );

        $this->addForeignKey(
            'fk_product_attributes_product_id',
            '{{%product_attributes}}',
            'product_id',
            '{{%products}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->addForeignKey(
            'fk_product_attributes_attribute_code',
            '{{%product_attributes}}',
            'attribute_code',
            '{{%attribute_definitions}}',
            'code',
            'RESTRICT',
            'CASCADE'
        );
    }

    public function safeDown(): void
    {
        $this->dropForeignKey('fk_product_attributes_attribute_code', '{{%product_attributes}}');
        $this->dropForeignKey('fk_product_attributes_product_id', '{{%product_attributes}}');
        $this->dropTable('{{%product_attributes}}');
    }
}
