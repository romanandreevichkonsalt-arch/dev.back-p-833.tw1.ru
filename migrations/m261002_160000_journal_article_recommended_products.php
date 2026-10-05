<?php

use yii\db\Migration;

class m261002_160000_journal_article_recommended_products extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%journal_article_recommended_products}}', [
            'id' => $this->primaryKey(),
            'journal_article_id' => $this->integer()->notNull(),
            'catalog_product_id' => $this->integer()->notNull(),
            'sort_order' => $this->smallInteger()->notNull()->defaultValue(0),
            'created_at' => $this->dateTime()->notNull(),
            'updated_at' => $this->dateTime()->notNull(),
        ]);

        $this->createIndex(
            'idx_journal_article_recommended_article_sort',
            '{{%journal_article_recommended_products}}',
            ['journal_article_id', 'sort_order', 'id']
        );
        $this->createIndex(
            'idx_journal_article_recommended_product',
            '{{%journal_article_recommended_products}}',
            'catalog_product_id'
        );
        $this->createIndex(
            'uidx_journal_article_recommended_pair',
            '{{%journal_article_recommended_products}}',
            ['journal_article_id', 'catalog_product_id'],
            true
        );

        $this->addForeignKey(
            'fk_journal_article_recommended_article',
            '{{%journal_article_recommended_products}}',
            'journal_article_id',
            '{{%journal_articles}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
        $this->addForeignKey(
            'fk_journal_article_recommended_product',
            '{{%journal_article_recommended_products}}',
            'catalog_product_id',
            '{{%catalog_products}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
    }

    public function safeDown(): void
    {
        $this->dropForeignKey('fk_journal_article_recommended_product', '{{%journal_article_recommended_products}}');
        $this->dropForeignKey('fk_journal_article_recommended_article', '{{%journal_article_recommended_products}}');
        $this->dropTable('{{%journal_article_recommended_products}}');
    }
}
