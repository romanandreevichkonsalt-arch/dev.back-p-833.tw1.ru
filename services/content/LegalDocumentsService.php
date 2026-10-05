<?php

namespace app\services\content;

use app\models\ContentBlock;
use app\models\ContentPage;
use app\services\cache\ApiResponseCache;
use app\services\media\MediaUrlResolver;

final class LegalDocumentsService
{
    private const PRIVACY_SLUG = 'privacy-policy';
    private const USER_AGREEMENT_SLUG = 'user-agreement';

    private MediaUrlResolver $mediaUrls;

    public function __construct(
        private readonly ApiResponseCache $cache = new ApiResponseCache(),
    ) {
        $this->mediaUrls = MediaUrlResolver::forPageContent();
    }

    /**
     * @return array{privacyPolicy: ?array<string, mixed>, userAgreement: ?array<string, mixed>}
     */
    public function getBoth(): array
    {
        $config = \Yii::$app->params['apiCache'] ?? [];

        return $this->cache->get(
            'pages',
            'legal-documents',
            fn (): array => $this->buildBoth(),
            (int)($config['pageTtl'] ?? 900)
        );
    }

    /**
     * @return array{privacyPolicy: ?array<string, mixed>, userAgreement: ?array<string, mixed>}
     */
    private function buildBoth(): array
    {
        return [
            'privacyPolicy' => $this->loadPageDocument(self::PRIVACY_SLUG),
            'userAgreement' => $this->loadPageDocument(self::USER_AGREEMENT_SLUG),
        ];
    }

    /**
     * @return array{slug: string, title: string, blocks: list<array<string, mixed>}>|null
     */
    private function loadPageDocument(string $slug): ?array
    {
        $page = ContentPage::find()
            ->where(['slug' => $slug, 'is_active' => true])
            ->with(['blocks' => static function ($query): void {
                $query->andWhere(['is_active' => true])->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC]);
            }])
            ->one();

        if ($page === null) {
            return null;
        }

        $blocks = $this->extractArticleBlocks($page);
        if ($blocks === []) {
            return null;
        }

        $payload = [
            'slug' => $slug,
            'title' => (string)$page->title,
            'blocks' => $blocks,
        ];

        /** @var array{slug: string, title: string, blocks: list<array<string, mixed>>} $resolved */
        $resolved = $this->mediaUrls->resolveTree($payload);

        return $resolved;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function extractArticleBlocks(ContentPage $page): array
    {
        foreach ($page->blocks as $block) {
            if (!$block instanceof ContentBlock || $block->block_key !== 'blocks') {
                continue;
            }

            $data = $block->getDataArray();
            if ($data === []) {
                return [];
            }

            if (isset($data['blocks']) && is_array($data['blocks'])) {
                return array_values(array_filter($data['blocks'], 'is_array'));
            }

            if (array_is_list($data)) {
                return array_values(array_filter($data, 'is_array'));
            }
        }

        return [];
    }
}
