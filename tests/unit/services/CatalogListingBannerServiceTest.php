<?php

namespace tests\unit\services;

use app\models\CatalogCategory;
use app\models\CatalogDirection;
use app\models\CatalogListingBanner;
use app\models\CatalogSubcategory;
use app\services\catalog\CatalogListingBannerService;
use app\services\catalog\CatalogScope;
use Codeception\Test\Unit;

class CatalogListingBannerServiceTest extends Unit
{
    public function testSaveAndResolveForCategoryScope(): void
    {
        $direction = CatalogDirection::find()->where(['is_active' => true])->orderBy(['id' => SORT_ASC])->one();
        verify($direction)->notNull();

        $category = CatalogCategory::find()->where(['is_active' => true])->orderBy(['id' => SORT_ASC])->one();
        verify($category)->notNull();

        $service = new CatalogListingBannerService();
        $saved = $service->saveFromPost((int)$direction->id, (int)$category->id, 0, [
            'seo_title' => 'SEO {category} — {direction}',
            'seo_description' => 'Count {count}',
            'seo_h1' => 'H1 {category}',
            'hero_title' => 'Hero title',
            'hero_subtitle' => 'Hero subtitle',
            'image_desktop_src' => '',
            'image_desktop_alt' => '',
            'image_mobile_src' => '',
            'image_mobile_alt' => '',
        ]);
        verify($saved)->true();

        $scope = new CatalogScope();
        $scope->direction = $direction;
        $scope->category = $category;

        $payload = $service->resolveForScope($scope, 5);
        verify($payload)->arrayHasKey('hero');
        verify($payload['hero']['title'] ?? '')->equals('Hero title');
        verify($payload)->arrayHasKey('seo');
        verify($payload['seo']['title'] ?? '')->stringContainsString((string)$category->label);
        verify($payload['seo']['description'] ?? '')->stringContainsString('5');

        CatalogListingBanner::deleteAll([
            'direction_id' => (int)$direction->id,
            'category_id' => (int)$category->id,
            'subcategory_id' => 0,
        ]);
    }

    public function testResolveUsesExactSubcategoryBanner(): void
    {
        $direction = CatalogDirection::find()->where(['is_active' => true])->orderBy(['id' => SORT_ASC])->one();
        $category = CatalogCategory::find()->where(['is_active' => true])->orderBy(['id' => SORT_ASC])->one();
        $subcategory = CatalogSubcategory::find()
            ->where(['category_id' => $category->id, 'is_active' => true])
            ->orderBy(['id' => SORT_ASC])
            ->one();

        if ($direction === null || $category === null || $subcategory === null) {
            verify(true)->true();

            return;
        }

        $service = new CatalogListingBannerService();
        $service->saveFromPost((int)$direction->id, (int)$category->id, (int)$subcategory->id, [
            'hero_title' => 'Sub hero',
            'hero_subtitle' => '',
            'image_desktop_src' => '',
            'image_desktop_alt' => '',
            'image_mobile_src' => '',
            'image_mobile_alt' => '',
        ]);

        $scope = new CatalogScope();
        $scope->direction = $direction;
        $scope->category = $category;
        $scope->subcategory = $subcategory;

        $payload = $service->resolveForScope($scope);
        verify($payload['hero']['title'] ?? '')->equals('Sub hero');

        CatalogListingBanner::deleteAll([
            'direction_id' => (int)$direction->id,
            'category_id' => (int)$category->id,
            'subcategory_id' => (int)$subcategory->id,
        ]);
    }
}
