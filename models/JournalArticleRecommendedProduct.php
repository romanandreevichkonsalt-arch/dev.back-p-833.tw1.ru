<?php

namespace app\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

class JournalArticleRecommendedProduct extends ActiveRecord
{
    public const MAX_PER_ARTICLE = 12;

    public static function tableName(): string
    {
        return '{{%journal_article_recommended_products}}';
    }

    public function behaviors(): array
    {
        return [
            'timestamp' => [
                'class' => TimestampBehavior::class,
                'value' => static fn (): string => date('Y-m-d H:i:s'),
            ],
        ];
    }

    public function rules(): array
    {
        return [
            [['journal_article_id', 'catalog_product_id'], 'required'],
            [['journal_article_id', 'catalog_product_id', 'sort_order'], 'integer'],
            [['catalog_product_id'], 'exist', 'targetClass' => CatalogProduct::class, 'targetAttribute' => ['catalog_product_id' => 'id']],
            [['journal_article_id'], 'exist', 'targetClass' => JournalArticle::class, 'targetAttribute' => ['journal_article_id' => 'id']],
        ];
    }

    public function getJournalArticle()
    {
        return $this->hasOne(JournalArticle::class, ['id' => 'journal_article_id']);
    }

    public function getCatalogProduct()
    {
        return $this->hasOne(CatalogProduct::class, ['id' => 'catalog_product_id']);
    }
}
