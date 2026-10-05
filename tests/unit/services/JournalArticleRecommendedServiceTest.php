<?php

namespace tests\unit\services;

use app\models\CatalogProduct;
use app\models\JournalArticle;
use app\models\JournalArticleRecommendedProduct;
use app\services\journal\JournalArticleRecommendedService;
use Codeception\Test\Unit;
use Yii;

class JournalArticleRecommendedServiceTest extends Unit
{
    private JournalArticleRecommendedService $service;

    protected function _before(): void
    {
        $this->service = new JournalArticleRecommendedService();
    }

    public function testParseProductIdsFromPostDedupesAndLimits(): void
    {
        $ids = $this->service->parseProductIdsFromPost([
            'recommended_products' => [
                ['catalog_product_id' => '5'],
                ['catalog_product_id' => '5'],
                ['catalog_product_id' => '9'],
            ],
        ]);

        verify($ids)->equals([5, 9]);
    }

    public function testSyncAndBuildApiProducts(): void
    {
        if (Yii::$app->db->schema->getTableSchema(JournalArticleRecommendedProduct::tableName(), true) === null) {
            $this->markTestSkipped('journal_article_recommended_products table missing');
        }

        $article = JournalArticle::find()->one();
        $product = CatalogProduct::find()->where(['is_active' => true, 'is_custom' => false])->one();
        if ($article === null || $product === null) {
            $this->markTestSkipped('Need journal article and catalog product fixtures');
        }

        $articleId = (int)$article->id;
        $productId = (int)$product->id;

        JournalArticleRecommendedProduct::deleteAll(['journal_article_id' => $articleId]);

        try {
            $this->service->syncForArticle($articleId, [$productId]);
            $items = $this->service->buildApiProducts($article);
            verify($items)->notEmpty();
            verify($items[0]['slug'] ?? null)->equals($product->slug);
            verify(isset($items[0]['title'], $items[0]['image']))->true();
        } finally {
            JournalArticleRecommendedProduct::deleteAll(['journal_article_id' => $articleId]);
        }
    }
}
