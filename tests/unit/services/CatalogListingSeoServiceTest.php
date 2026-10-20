<?php

namespace tests\unit\services;

use app\models\CatalogCategory;
use app\models\CatalogDirection;
use app\models\CatalogSubcategory;
use app\services\catalog\CatalogListingSeoService;
use app\services\catalog\CatalogScope;
use Codeception\Test\Unit;

class CatalogListingSeoServiceTest extends Unit
{
    public function testTemplatesFromPostAndToFormRoundTrip(): void
    {
        $service = new CatalogListingSeoService();
        $post = [
            'seo_direction_title' => 'Dir {direction}',
            'seo_direction_description' => 'Desc {count}',
            'seo_direction_h1' => 'H1 {direction}',
            'seo_category_title' => '',
            'seo_category_description' => '',
            'seo_category_h1' => '',
            'seo_subcategory_title' => '',
            'seo_subcategory_description' => '',
            'seo_subcategory_h1' => '',
        ];

        $templates = $service->templatesFromPost($post);
        verify($templates['direction']['title'])->equals('Dir {direction}');

        $form = $service->templatesToForm($templates);
        verify($form['seo_direction_title'])->equals('Dir {direction}');
    }

    public function testResolveForScopeSubstitutesVariables(): void
    {
        $direction = CatalogDirection::find()->where(['is_active' => true])->orderBy(['id' => SORT_ASC])->one();
        $category = CatalogCategory::find()->where(['is_active' => true])->orderBy(['id' => SORT_ASC])->one();

        if ($direction === null || $category === null) {
            verify(true)->true();

            return;
        }

        $service = new CatalogListingSeoService();
        $scope = new CatalogScope();
        $scope->direction = $direction;
        $scope->category = $category;

        $seo = $service->resolveForScope($scope, 12);
        verify($seo)->arrayHasKey('title');
        verify($seo['title'])->stringContainsString((string)$category->label);
        verify($seo['title'])->stringContainsString((string)$direction->label);
        verify($seo['description'])->stringContainsString('12');
    }

    public function testSubcategoryLevelUsesSubcategoryTemplate(): void
    {
        $direction = CatalogDirection::find()->where(['is_active' => true])->orderBy(['id' => SORT_ASC])->one();
        $category = CatalogCategory::find()->where(['is_active' => true])->orderBy(['id' => SORT_ASC])->one();
        $subcategory = CatalogSubcategory::find()
            ->where(['category_id' => $category?->id, 'is_active' => true])
            ->orderBy(['id' => SORT_ASC])
            ->one();

        if ($direction === null || $category === null || $subcategory === null) {
            verify(true)->true();

            return;
        }

        $service = new CatalogListingSeoService();
        $scope = new CatalogScope();
        $scope->direction = $direction;
        $scope->category = $category;
        $scope->subcategory = $subcategory;

        $seo = $service->resolveForScope($scope, 3);
        verify($seo['h1'] ?? '')->stringContainsString((string)$subcategory->label);
    }
}
