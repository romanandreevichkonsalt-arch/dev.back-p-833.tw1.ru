<?php

namespace app\services\catalog;

use app\models\CatalogCategory;
use app\models\CatalogDirection;
use app\models\CatalogListingBanner;
use app\models\CatalogSubcategory;
use app\services\content\BlockFormHandler;
use app\services\content\BlockTypeRegistry;
use app\services\content\HeroBannerPayload;
use app\services\media\MediaUrlResolver;

final class CatalogListingBannerService
{
    public function __construct(
        private readonly ?MediaUrlResolver $mediaUrls = null,
        private readonly ?CatalogListingSeoService $listingSeo = null,
    ) {
    }

    private function mediaUrls(): MediaUrlResolver
    {
        return $this->mediaUrls ?? MediaUrlResolver::forPageContent();
    }

    private function listingSeo(): CatalogListingSeoService
    {
        return $this->listingSeo ?? new CatalogListingSeoService();
    }

    /**
     * @return list<array{
     *     kind: string,
     *     directionId: int,
     *     categoryId: int,
     *     subcategoryId: int,
     *     label: string,
     *     configured: bool
     * }>
     */
    public function buildScopeNavItems(CatalogDirection $direction): array
    {
        $items = [];
        $items[] = [
            'kind' => 'direction',
            'directionId' => (int)$direction->id,
            'categoryId' => 0,
            'subcategoryId' => 0,
            'label' => (string)$direction->label,
            'configured' => $this->isScopeConfigured((int)$direction->id, 0, 0),
        ];

        $categories = CatalogCategory::find()
            ->where(['is_active' => true])
            ->with(['subcategories'])
            ->orderBy(['sort_order' => SORT_ASC, 'label' => SORT_ASC])
            ->all();
        foreach ($categories as $category) {
            $items[] = [
                'kind' => 'category',
                'directionId' => (int)$direction->id,
                'categoryId' => (int)$category->id,
                'subcategoryId' => 0,
                'label' => (string)$category->label,
                'configured' => $this->isScopeConfigured((int)$direction->id, (int)$category->id, 0),
            ];

            foreach ($category->subcategories as $subcategory) {
                if (!$subcategory->is_active) {
                    continue;
                }

                $items[] = [
                    'kind' => 'subcategory',
                    'directionId' => (int)$direction->id,
                    'categoryId' => (int)$category->id,
                    'subcategoryId' => (int)$subcategory->id,
                    'label' => (string)$subcategory->label,
                    'configured' => $this->isScopeConfigured(
                        (int)$direction->id,
                        (int)$category->id,
                        (int)$subcategory->id
                    ),
                ];
            }
        }

        return $items;
    }

    /**
     * @return array<string, mixed>
     */
    public function buildAdminFormData(int $directionId, int $categoryId, int $subcategoryId): array
    {
        $banner = $this->findBannerRow($directionId, $categoryId, $subcategoryId);
        if ($banner === null) {
            $defaults = $this->listingSeo()->defaultTemplateForAdminScope($categoryId, $subcategoryId);

            return array_merge(
                $this->seoToForm($defaults),
                [
                    'hero_title' => '',
                    'hero_subtitle' => '',
                    'image_desktop_src' => '',
                    'image_desktop_alt' => '',
                    'image_mobile_src' => '',
                    'image_mobile_alt' => '',
                ]
            );
        }

        return array_merge(
            $this->seoToForm($banner->getSeoArray()),
            $this->heroToForm($banner->getHeroArray())
        );
    }

    /**
     * @param array<string, mixed> $post
     */
    public function saveFromPost(int $directionId, int $categoryId, int $subcategoryId, array $post): bool
    {
        $handler = new BlockFormHandler();
        $seo = $handler->dataFromPost(BlockTypeRegistry::TYPE_SEO, $post);
        $seo['h1'] = trim((string)($post['seo_h1'] ?? ''));
        $hero = $this->heroFromPost($post);

        $banner = $this->findBannerRow($directionId, $categoryId, $subcategoryId);
        if ($banner === null) {
            $banner = new CatalogListingBanner([
                'direction_id' => $directionId,
                'category_id' => $categoryId,
                'subcategory_id' => $subcategoryId,
            ]);
        }

        $banner->setScopePayload($seo, $hero);

        return $banner->save(false);
    }

    /**
     * @return array{hero?: array<string, mixed>, seo?: array<string, mixed>}
     */
    public function resolveForScope(CatalogScope $scope, ?int $productCount = null): array
    {
        [$directionId, $categoryId, $subcategoryId] = $this->scopeIds($scope);
        if ($directionId === null) {
            return [];
        }

        $result = [];
        $banner = $this->findBannerRow($directionId, $categoryId, $subcategoryId);

        $seoRaw = $banner !== null ? $banner->getSeoArray() : [];
        $seo = $this->listingSeo()->renderStoredSeo($seoRaw, $scope, $productCount);
        if ($seo === []) {
            $seo = $this->listingSeo()->resolveFallbackForScope($scope, $productCount);
        }
        if ($seo !== []) {
            $result['seo'] = $seo;
        }

        if ($banner !== null) {
            $hero = $this->normalizeHeroApiPayload($banner->getHeroArray());
            if ($hero !== []) {
                $result['hero'] = $hero;
            }
        }

        if ($result === []) {
            return [];
        }

        return $this->mediaUrls()->resolveTree($result);
    }

    public function scopeLabel(int $directionId, int $categoryId, int $subcategoryId): string
    {
        if ($subcategoryId > 0) {
            $subcategory = CatalogSubcategory::findOne($subcategoryId);

            return $subcategory !== null ? (string)$subcategory->label : 'Подкатегория';
        }

        if ($categoryId > 0) {
            $category = CatalogCategory::findOne($categoryId);

            return $category !== null ? (string)$category->label : 'Категория';
        }

        $direction = CatalogDirection::findOne($directionId);

        return $direction !== null ? (string)$direction->label : 'Направление';
    }

    public function scopeLead(int $directionId, int $categoryId, int $subcategoryId): string
    {
        if ($subcategoryId > 0) {
            return 'SEO и баннер подкатегории в выбранном направлении.';
        }

        if ($categoryId > 0) {
            return 'SEO и баннер категории в выбранном направлении.';
        }

        return 'SEO и баннер корня направления в каталоге.';
    }

    private function isScopeConfigured(int $directionId, int $categoryId, int $subcategoryId): bool
    {
        $banner = $this->findBannerRow($directionId, $categoryId, $subcategoryId);
        if ($banner === null) {
            return false;
        }

        if ($this->normalizeHeroApiPayload($banner->getHeroArray()) !== []) {
            return true;
        }

        $seo = $banner->getSeoArray();

        return trim((string)($seo['title'] ?? '')) !== ''
            || trim((string)($seo['description'] ?? '')) !== ''
            || trim((string)($seo['h1'] ?? '')) !== '';
    }

    private function findBannerRow(int $directionId, int $categoryId, int $subcategoryId): ?CatalogListingBanner
    {
        return CatalogListingBanner::find()
            ->where([
                'direction_id' => $directionId,
                'category_id' => $categoryId,
                'subcategory_id' => $subcategoryId,
            ])
            ->one();
    }

    /**
     * @return array{0: ?int, 1: int, 2: int}
     */
    private function scopeIds(CatalogScope $scope): array
    {
        if ($scope->direction === null && $scope->collection?->direction !== null) {
            $scope->direction = $scope->collection->direction;
        }

        if ($scope->direction === null) {
            return [null, 0, 0];
        }

        $directionId = (int)$scope->direction->id;
        $categoryId = $scope->category !== null ? (int)$scope->category->id : 0;
        $subcategoryId = $scope->subcategory !== null ? (int)$scope->subcategory->id : 0;

        return [$directionId, $categoryId, $subcategoryId];
    }

    /**
     * @param array<string, mixed> $hero
     * @return array<string, mixed>
     */
    private function normalizeHeroApiPayload(array $hero): array
    {
        $heroNormalized = HeroBannerPayload::normalize(array_merge($hero, [
            'title' => $hero['title'] ?? '',
            'subtitle' => $hero['subtitle'] ?? '',
        ]));

        if (isset($heroNormalized['collectionTitle']) && !isset($heroNormalized['title'])) {
            $heroNormalized['title'] = $heroNormalized['collectionTitle'];
        }
        if (isset($heroNormalized['tagline']) && !isset($heroNormalized['subtitle'])) {
            $heroNormalized['subtitle'] = $heroNormalized['tagline'];
        }

        unset($heroNormalized['collectionLabel'], $heroNormalized['label'], $heroNormalized['brandTitle']);
        if (($heroNormalized['collectionTitle'] ?? '') === ($heroNormalized['title'] ?? '')) {
            unset($heroNormalized['collectionTitle']);
        }
        if (($heroNormalized['tagline'] ?? '') === ($heroNormalized['subtitle'] ?? '')) {
            unset($heroNormalized['tagline']);
        }

        return array_filter($heroNormalized, static function (mixed $value): bool {
            if (is_string($value)) {
                return trim($value) !== '';
            }

            return $value !== null && $value !== [];
        });
    }

    /**
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    private function heroFromPost(array $post): array
    {
        $images = HeroBannerPayload::fromPost($post);

        return array_filter([
            'title' => trim((string)($post['hero_title'] ?? '')),
            'subtitle' => trim((string)($post['hero_subtitle'] ?? '')),
            'imageDesktop' => $images['imageDesktop'] ?? null,
            'imageMobile' => $images['imageMobile'] ?? null,
        ], static fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []);
    }

    /**
     * @param array<string, mixed> $seo
     * @return array<string, mixed>
     */
    private function seoToForm(array $seo): array
    {
        return [
            'seo_title' => trim((string)($seo['title'] ?? '')),
            'seo_description' => trim((string)($seo['description'] ?? '')),
            'seo_h1' => trim((string)($seo['h1'] ?? '')),
        ];
    }

    /**
     * @param array<string, mixed> $hero
     * @return array<string, mixed>
     */
    private function heroToForm(array $hero): array
    {
        $images = HeroBannerPayload::toForm($hero);

        return [
            'hero_title' => trim((string)($hero['title'] ?? $hero['collectionTitle'] ?? '')),
            'hero_subtitle' => trim((string)($hero['subtitle'] ?? $hero['tagline'] ?? '')),
            'image_desktop_src' => $images['image_desktop_src'] ?? '',
            'image_desktop_alt' => $images['image_desktop_alt'] ?? '',
            'image_mobile_src' => $images['image_mobile_src'] ?? '',
            'image_mobile_alt' => $images['image_mobile_alt'] ?? '',
        ];
    }
}
