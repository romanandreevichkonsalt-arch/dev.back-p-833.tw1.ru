<?php

namespace app\services\journal;

use app\models\JournalArticle;
use app\models\User;
use app\modules\admin\helpers\ContentPageJournalHelper;
use app\services\media\MediaUrlResolver;
use yii\web\NotFoundHttpException;

class JournalArticleService
{
    private MediaUrlResolver $mediaUrls;
    private JournalArticleRecommendedService $recommended;

    public function __construct(
        ?MediaUrlResolver $mediaUrls = null,
        ?JournalArticleRecommendedService $recommended = null,
    ) {
        $this->mediaUrls = $mediaUrls ?? MediaUrlResolver::forPageContent();
        $this->recommended = $recommended ?? new JournalArticleRecommendedService($this->mediaUrls);
    }
    /**
     * @param array<int, array<string, mixed>> $tabMeta
     * @return array<int, array<string, mixed>>
     */
    public function buildCategoriesWithArticles(array $tabMeta): array
    {
        $articlesByCategory = $this->loadActiveArticlesGrouped();

        $defaults = ContentPageJournalHelper::defaultCategoryTemplates();
        $metaById = [];

        foreach ($tabMeta as $row) {
            if (!is_array($row)) {
                continue;
            }

            $id = trim((string)($row['id'] ?? ''));
            if ($id !== '') {
                $metaById[$id] = $row;
            }
        }

        $result = [];
        foreach ($defaults as $template) {
            $id = $template['id'];
            $merged = $metaById[$id] ?? [];
            $icon = trim((string)($merged['icon'] ?? '')) ?: $template['icon'];
            $label = trim((string)($merged['label'] ?? '')) ?: $template['label'];

            $category = array_filter([
                'id' => $id,
                'label' => $label,
                'icon' => $icon,
            ], static fn (string $v): bool => $v !== '');

            $items = [];
            foreach ($articlesByCategory[$id] ?? [] as $article) {
                $items[] = $this->buildCardItem($article);
            }

            $category['items'] = $items;
            $result[] = $category;
        }

        return $result;
    }

    /**
     * @return array<string, array<int, JournalArticle>>
     */
    private function loadActiveArticlesGrouped(): array
    {
        $articles = JournalArticle::find()
            ->where(['is_active' => true])
            ->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_DESC])
            ->all();

        $grouped = [];
        foreach ($articles as $article) {
            $grouped[$article->category_id][] = $article;
        }

        foreach ($grouped as $categoryId => $list) {
            $grouped[$categoryId] = $this->sortArticles($list);
        }

        return $grouped;
    }

    /**
     * @param array<int, JournalArticle> $articles
     * @return array<int, JournalArticle>
     */
    public function sortArticles(array $articles): array
    {
        usort($articles, static function (JournalArticle $a, JournalArticle $b): int {
            $dateA = self::parseDate($a->date);
            $dateB = self::parseDate($b->date);

            if ($dateA !== $dateB) {
                return $dateB <=> $dateA;
            }

            return (int)$b->id <=> (int)$a->id;
        });

        return $articles;
    }

    /**
     * @return array<string, mixed>
     */
    public function buildCardItem(JournalArticle $article): array
    {
        $slug = trim($article->slug);
        $to = '/journal/' . $slug;

        $item = array_filter([
            'id' => (int)$article->id,
            'slug' => $slug,
            'title' => trim($article->title),
            'date' => trim($article->date),
            'excerpt' => trim($article->excerpt),
            'to' => $to,
            'imagePosition' => trim($article->image_position),
        ], static fn (string $v): bool => $v !== '');

        $src = trim($article->image_src);
        if ($src !== '') {
            $image = $this->mediaUrls->resolveImagePayload(
                $src,
                trim($article->image_alt) ?: trim($article->title)
            );
            if ($image !== null) {
                $item['image'] = $image;
            }
        }

        return $item;
    }

    /**
     * @return array<string, mixed>
     */
    public function buildArticlePayload(JournalArticle $article, ?User $dealer = null): array
    {
        $seoTitle = trim($article->seo_title);
        $seoDescription = trim($article->seo_description);

        $payload = [
            'slug' => trim($article->slug),
            'title' => trim($article->title),
            'date' => trim($article->date),
            'readingTime' => trim($article->reading_time),
            'categoryId' => trim($article->category_id),
            'blocks' => $article->getBlocksArray(),
        ];

        $recommendedProducts = $this->recommended->buildApiProducts($article, $dealer);
        if ($recommendedProducts !== []) {
            $payload['recommendedProducts'] = $recommendedProducts;
        }

        if ($seoTitle !== '' || $seoDescription !== '') {
            $payload['seo'] = array_filter([
                'title' => $seoTitle,
                'description' => $seoDescription,
            ], static fn (string $v): bool => $v !== '');
        }

        return $this->mediaUrls->resolveTree($payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function getBySlug(string $slug, ?User $dealer = null): array
    {
        $slug = trim($slug);
        if ($slug === '') {
            throw new NotFoundHttpException('Статья не найдена.');
        }

        $article = JournalArticle::find()
            ->where(['slug' => $slug, 'is_active' => true])
            ->one();

        if ($article === null) {
            throw new NotFoundHttpException('Статья не найдена.');
        }

        return $this->buildArticlePayload($article, $dealer);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function buildLatestArticlesForHome(int $limit = 3): array
    {
        $articles = JournalArticle::find()
            ->where(['is_active' => true])
            ->limit(100)
            ->all();

        $sorted = $this->sortArticles($articles);
        $latest = array_slice($sorted, 0, $limit);

        $result = [];
        foreach ($latest as $index => $article) {
            $card = $this->buildCardItem($article);
            $card['number'] = sprintf('%02d', $index + 1);
            $result[] = $card;
        }

        return $result;
    }

    /**
     * @return array<string, int>
     */
    public function countByCategory(): array
    {
        $grouped = $this->articlesByCategoryForAdmin();
        $result = [];
        foreach ($grouped as $categoryId => $articles) {
            $result[$categoryId] = count($articles);
        }

        return $result;
    }

    /**
     * @return array<string, array<int, JournalArticle>>
     */
    public function articlesByCategoryForAdmin(): array
    {
        $articles = JournalArticle::find()
            ->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_DESC])
            ->all();

        $grouped = [];
        foreach ($articles as $article) {
            $grouped[$article->category_id][] = $article;
        }

        foreach ($grouped as $categoryId => $list) {
            $grouped[$categoryId] = $this->sortArticles($list);
        }

        return $grouped;
    }

    private static function parseDate(string $date): int
    {
        $date = trim($date);
        if ($date === '') {
            return 0;
        }

        $parsed = \DateTime::createFromFormat('d.m.Y', $date);
        if ($parsed instanceof \DateTime) {
            return (int)$parsed->format('U');
        }

        $timestamp = strtotime($date);

        return $timestamp !== false ? $timestamp : 0;
    }
}
