<?php

namespace app\services\content;

use app\models\ContentPage;
use app\modules\admin\helpers\HomePageJournalHelper;
use app\services\cache\ApiResponseCache;
use app\services\content\ContentFallbackTrait;
use app\services\journal\JournalArticleService;
use app\services\vacancy\VacancyService;
use app\services\media\MediaUrlResolver;
use yii\web\NotFoundHttpException;

class PageContentService
{
    use ContentFallbackTrait;

    private JsonContentService $jsonContent;
    private MediaUrlResolver $mediaUrls;
    private ApiResponseCache $cache;

    public function __construct(?ApiResponseCache $cache = null)
    {
        $this->jsonContent = new JsonContentService();
        $this->mediaUrls = MediaUrlResolver::forPageContent();
        $this->cache = $cache ?? \Yii::$container->get(ApiResponseCache::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function getPage(string $slug): array
    {
        $slug = trim($slug);
        $config = \Yii::$app->params['apiCache'] ?? [];

        $result = $this->cache->get(
            'pages',
            $slug,
            fn (): array => $this->buildPage($slug),
            (int)($config['pageTtl'] ?? 900)
        );

        return $this->applyHomePageOverrides($slug, $result);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPage(string $slug): array
    {
        if (!$this->hasPageInDb($slug)) {
            $result = $this->fallbackOrThrow(
                fn (): array => $this->jsonContent->getPage($slug),
                'Страница не найдена.'
            );

            $result = $this->applyPageEntityOverrides($slug, $result);

            return $this->mediaUrls->resolveTree($result);
        }

        $page = ContentPage::find()
            ->where(['slug' => $slug, 'is_active' => true])
            ->with(['blocks' => static function ($query) {
                $query->andWhere(['is_active' => true])->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC]);
            }])
            ->one();

        if ($page === null) {
            throw new NotFoundHttpException('Страница не найдена.');
        }

        $result = [];
        foreach ($page->blocks as $block) {
            $result[$block->block_key] = $block->getDataArray();
        }

        $result = $this->applyPageEntityOverrides($slug, $result);

        return $this->mediaUrls->resolveTree($result);
    }

    /**
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    private function applyPageEntityOverrides(string $slug, array $result): array
    {
        $result = $this->applyJournalPageOverrides($slug, $result);
        $result = $this->applyVacanciesPageOverrides($slug, $result);

        return $this->applyAboutPageOverrides($slug, $result);
    }

    /**
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    private function applyAboutPageOverrides(string $slug, array $result): array
    {
        if ($slug !== 'about') {
            return $result;
        }

        unset($result['jobs']);
        $result['jobs'] = (new VacancyService())->buildAboutJobs();

        return $result;
    }

    /**
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    private function applyVacanciesPageOverrides(string $slug, array $result): array
    {
        if ($slug !== 'vacancies') {
            return $result;
        }

        $result['groups'] = (new VacancyService())->buildGroupsWithJobs();

        return $result;
    }

    /**
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    private function applyJournalPageOverrides(string $slug, array $result): array
    {
        if ($slug !== 'journal' || !isset($result['categories'])) {
            return $result;
        }

        $tabMeta = $result['categories'];
        if (!is_array($tabMeta)) {
            return $result;
        }

        $service = new JournalArticleService();
        $result['categories'] = $service->buildCategoriesWithArticles($tabMeta);

        return $result;
    }

    private function applyHomePageOverrides(string $slug, array $result): array
    {
        if ($slug !== 'home') {
            return $result;
        }

        $config = \Yii::$app->params['apiCache'] ?? [];
        $pageTtl = (int)($config['pageTtl'] ?? 900);

        $result['journal'] = $this->cache->get(
            'pages',
            'home:journal-latest',
            static fn (): array => HomePageJournalHelper::buildLatestArticlesPayload(),
            $pageTtl
        );

        if (isset($result['products']) && is_array($result['products'])) {
            $productsConfig = $result['products'];
            $productsKey = 'home:products-enriched:' . md5(json_encode($productsConfig, JSON_THROW_ON_ERROR));
            $result['products'] = $this->cache->get(
                'pages',
                $productsKey,
                fn (): array => (new HomePageProductsEnricher())->enrich($productsConfig),
                $pageTtl
            );
        }

        return $result;
    }

    private function hasPageInDb(string $slug): bool
    {
        try {
            return ContentPage::find()->where(['slug' => $slug])->exists();
        } catch (\Throwable) {
            return false;
        }
    }
}
