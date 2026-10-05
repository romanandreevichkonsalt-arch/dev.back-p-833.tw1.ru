<?php

namespace app\services\journal;

use app\models\CatalogProduct;
use app\models\JournalArticle;
use app\models\JournalArticleRecommendedProduct;
use app\models\User;
use app\modules\admin\helpers\HomePageProductsHelper;
use app\services\media\MediaUrlResolver;
use app\services\search\SearchDocumentBuilder;
use yii\db\Exception as DbException;

class JournalArticleRecommendedService
{
    private MediaUrlResolver $mediaUrls;
    private SearchDocumentBuilder $documentBuilder;

    public function __construct(
        ?MediaUrlResolver $mediaUrls = null,
        ?SearchDocumentBuilder $documentBuilder = null,
    ) {
        $this->mediaUrls = $mediaUrls ?? MediaUrlResolver::forPageContent();
        $this->documentBuilder = $documentBuilder ?? new SearchDocumentBuilder();
    }

    /**
     * @param array<string, mixed> $post
     * @return list<int>
     */
    public function parseProductIdsFromPost(array $post): array
    {
        $rows = $post['recommended_products'] ?? [];
        if (!is_array($rows)) {
            return [];
        }

        $ids = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (int)($row['catalog_product_id'] ?? 0);
            if ($id <= 0 || isset($ids[$id])) {
                continue;
            }
            $ids[$id] = true;
            if (count($ids) >= JournalArticleRecommendedProduct::MAX_PER_ARTICLE) {
                break;
            }
        }

        return array_keys($ids);
    }

    /**
     * @param list<int> $productIds
     */
    public function syncForArticle(int $journalArticleId, array $productIds): void
    {
        if ($journalArticleId <= 0) {
            return;
        }

        $productIds = array_values(array_filter(
            array_map(static fn ($id): int => (int)$id, $productIds),
            static fn (int $id): bool => $id > 0
        ));
        $productIds = array_slice($productIds, 0, JournalArticleRecommendedProduct::MAX_PER_ARTICLE);

        $existing = JournalArticleRecommendedProduct::find()
            ->where(['journal_article_id' => $journalArticleId])
            ->indexBy('catalog_product_id')
            ->all();

        $sortOrder = 0;
        foreach ($productIds as $productId) {
            $row = $existing[$productId] ?? null;
            if ($row === null) {
                $row = new JournalArticleRecommendedProduct([
                    'journal_article_id' => $journalArticleId,
                    'catalog_product_id' => $productId,
                ]);
            }
            $row->sort_order = $sortOrder++;
            if (!$row->save(false)) {
                throw new DbException('Не удалось сохранить рекомендуемые товары статьи.');
            }
            unset($existing[$productId]);
        }

        foreach ($existing as $orphan) {
            $orphan->delete();
        }
    }

    /**
     * @return list<array{catalog_product_id: int|string, product_search: string}>
     */
    public function buildAdminRows(?JournalArticle $article): array
    {
        if ($article === null || $article->isNewRecord) {
            return [];
        }

        $rows = JournalArticleRecommendedProduct::find()
            ->where(['journal_article_id' => (int)$article->id])
            ->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC])
            ->all();

        $formRows = [];
        foreach ($rows as $row) {
            $productId = (int)$row->catalog_product_id;
            $picker = HomePageProductsHelper::pickerItemByProductId($productId);
            $formRows[] = [
                'catalog_product_id' => $productId,
                'product_search' => $picker['productTitle'] ?? $picker['title'] ?? '',
            ];
        }

        return $formRows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function buildApiProducts(JournalArticle $article, ?User $dealer = null): array
    {
        $links = JournalArticleRecommendedProduct::find()
            ->where(['journal_article_id' => (int)$article->id])
            ->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC])
            ->with([
                'catalogProduct.image',
                'catalogProduct.badge.image',
                'catalogProduct.subcategory',
                'catalogProduct.collection.direction',
                'catalogProduct.catalogModel.modelImages.media',
                'catalogProduct.fabricColor.catalogColor',
            ])
            ->all();

        $items = [];
        foreach ($links as $link) {
            $product = $link->catalogProduct;
            if ($product === null || !(bool)$product->is_active || (bool)$product->is_custom) {
                continue;
            }

            $document = $product->toSearchIndexDocument($dealer);
            $items[] = $this->documentBuilder->toPublicProduct($document);
        }

        if ($items === []) {
            return [];
        }

        return $this->mediaUrls->resolveTree($items);
    }
}
