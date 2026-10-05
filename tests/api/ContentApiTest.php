<?php

namespace tests\api;

use Yii;

class ContentApiTest extends ApiTestCase
{
    public function testPing(): void
    {
        $response = $this->getJson('api/v1/ping/index');

        verify($response['status'])->equals('ok');
        verify($response['service'])->equals('fabrika-backend-api');
    }

    public function testPagesHome(): void
    {
        $response = $this->getJson('api/v1/pages/home');

        verify($response)->arrayHasKey('seo');
        verify($response)->arrayHasKey('hero');
        verify($response)->arrayHasKey('collections');
        verify($response)->arrayHasKey('products');
    }

    public function testPagesContactsHasRegions(): void
    {
        $response = $this->getJson('api/v1/pages/contacts');

        verify($response)->arrayHasKey('regions');
        verify($response['regions'])->notEmpty();
        verify($response['regions'][0]['stores'][0])->arrayHasKey('coordinates');
        verify($response)->arrayHasKey('contact');
    }

    public function testPagesLegalDocuments(): void
    {
        $response = $this->getJson('api/v1/pages/legal-documents');

        verify($response)->arrayHasKey('privacyPolicy');
        verify($response)->arrayHasKey('userAgreement');
    }

    public function testPagesPartnersReturnsAllBlocks(): void
    {
        $response = $this->getJson('api/v1/pages/partners');

        foreach ([
            'seo',
            'hero',
            'intro',
            'mission',
            'formats',
            'audience',
            'salonFormats',
            'gallery',
            'terms',
            'presentation',
        ] as $key) {
            verify($response)->arrayHasKey($key);
        }

        verify($response['hero'])->arrayHasKey('title');
        verify($response['hero'])->arrayHasKey('subtitle');
        verify($response['hero'])->arrayHasKey('imageDesktop');
        verify($response['mission']['items'])->notEmpty();
    }

    public function testPagesAboutReturnsBlocksAndJobs(): void
    {
        $response = $this->getJson('api/v1/pages/about');

        foreach (['seo', 'hero', 'intro', 'community', 'timeline', 'jobsIntro', 'jobs'] as $key) {
            verify($response)->arrayHasKey($key);
        }

        verify($response['hero'])->arrayHasKey('label');
        verify($response['hero'])->arrayHasKey('brandTitle');
        verify($response['intro'])->arrayHasKey('paragraphs');
    }

    public function testPagesVacanciesReturnsGroups(): void
    {
        $response = $this->getJson('api/v1/pages/vacancies');

        foreach (['seo', 'hero', 'gallery', 'values', 'groups'] as $key) {
            verify($response)->arrayHasKey($key);
        }

        verify($response['groups'])->notEmpty();
        verify($response['groups'][0])->arrayHasKey('jobs');
    }

    public function testVacancyDetailNotFound(): void
    {
        $this->expectException(\yii\web\NotFoundHttpException::class);
        $_SERVER['REQUEST_METHOD'] = 'GET';
        \Yii::$app->runAction('api/v1/vacancies/view', ['slug' => 'missing-vacancy-slug']);
    }

    public function testCatalogMenuHeadTotal(): void
    {
        $this->headCatalog([
            'subcategory' => 'straight',
        ]);

        verify($this->getResponseHeader('X-Total-Count'))->notNull();
        verify((int)$this->getResponseHeader('X-Total-Count'))->greaterThan(-1);
        verify($this->getResponseHeader('X-Scope-Mode'))->equals('shortcut');
    }

    public function testCatalogMenuHeadWithFrontendSlugs(): void
    {
        $this->headCatalog([
            'collection' => 'a-plus',
            'category' => 'divany',
            'subcategory' => 'pryamye',
        ]);

        verify($this->getResponseHeader('X-Total-Count'))->notNull();
        verify($this->getResponseHeader('X-Scope-Mode'))->equals('chain');
    }

    public function testCatalogMenu(): void
    {
        $response = $this->getJson('api/v1/catalog/menu');

        verify($response)->arrayHasKey('groups');
        verify($response)->arrayHasKey('collections');
        verify($response)->arrayHasKey('modelLines');
        verify($response)->arrayHasKey('categories');
        verify($response)->arrayHasKey('navigationItems');
        verify($response)->arrayHasKey('items');
        verify($response)->arrayHasKey('filters');
    }

    public function testCatalogMenuWithFrontendSlugs(): void
    {
        $response = $this->getJson('api/v1/catalog/menu', [
            'collection' => 'a-plus',
            'category' => 'divany',
            'subcategory' => 'pryamye',
        ]);

        verify($response)->arrayHasKey('items');
        verify($response)->arrayHasKey('meta');
        if ($response['items'] !== []) {
            verify($response['items'][0])->arrayHasKey('slug');
            verify($response['items'][0])->arrayHasKey('retailPrice');
            verify($response['items'][0]['href'])->stringStartsWith('/product/');
        }
    }

    public function testCatalogProductDetailNotFound(): void
    {
        $this->expectException(\yii\web\NotFoundHttpException::class);
        $_SERVER['REQUEST_METHOD'] = 'GET';
        \Yii::$app->runAction('api/v1/catalog/product', ['slug' => 'non-existent-slug-xyz']);
    }

    public function testSearchProductsPagination(): void
    {
        $response = $this->getJson('api/v1/search/products', ['q' => 'турин']);

        verify($response)->arrayHasKey('items');
        verify($response)->arrayHasKey('meta');
        verify($response['meta'])->arrayHasKey('total');
        verify($response['meta'])->arrayHasKey('page');
    }

    public function testSearchProductHrefs(): void
    {
        $response = $this->getJson('api/v1/search/index', ['q' => 'турин']);

        if ($response['products'] !== []) {
            verify($response['products'][0]['href'])->stringStartsWith('/product/');
            verify($response['products'][0]['to'])->stringStartsWith('/product/');
        }
    }

    public function testCatalogMenuProducts(): void
    {
        $response = $this->getJson('api/v1/catalog/menu', [
            'subcategory' => 'straight',
        ]);

        verify($response)->arrayHasKey('items');
        verify($response)->arrayHasKey('filters');
        verify($response)->arrayHasKey('meta');
        verify($response)->arrayHasKey('collections');
    }

    public function testCatalogNavigationRoot(): void
    {
        $response = $this->getJson('api/v1/catalog/menu');

        verify($response['level'])->equals('direction');
        verify($response['navigationItems'])->notEmpty();
    }

    public function testCatalogProductsShortcut(): void
    {
        $response = $this->getJson('api/v1/catalog/menu', [
            'subcategory' => 'straight',
        ]);

        verify($response)->arrayHasKey('items');
        verify($response)->arrayHasKey('filters');
        verify($response['meta']['scopeMode'])->equals('shortcut');
    }

    public function testCatalogMenuByPathSubcategory(): void
    {
        $response = $this->getJsonByPath('straight');

        verify($response)->arrayHasKey('items');
        verify($response)->arrayHasKey('meta');
        verify($response['meta']['scopeMode'])->equals('shortcut');
        verify($response['meta']['applied']['subcategory'])->notEmpty();
    }

    public function testCatalogMenuByPathChain(): void
    {
        $response = $this->getJsonByPath('a-plus/divany/pryamye');

        verify($response)->arrayHasKey('items');
        verify($response['meta']['scopeMode'])->equals('chain');
        verify($response['meta']['applied']['direction'])->equals('a-plus');
        verify($response['meta']['applied']['category'])->equals('divany');
        verify($response['meta']['applied']['subcategory'])->equals('pryamye');
    }

    public function testCatalogMenuByPathUnknown(): void
    {
        $this->expectException(\yii\web\NotFoundHttpException::class);
        $this->getJsonByPath('definitely-not-a-catalog-slug-xyz');
    }

    /**
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    private function getJsonByPath(string $slugs, array $query = []): array
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        Yii::$app->request->setQueryParams($query);

        return Yii::$app->runAction('api/v1/catalog/menu', ['slugs' => $slugs]);
    }

    public function testSearchBootstrap(): void
    {
        $response = $this->getJson('api/v1/search/bootstrap');

        verify($response)->arrayHasKey('frequent');
        verify($response)->arrayHasKey('categories');
        verify($response)->arrayHasKey('recommended');
        verify($response)->arrayHasKey('recommendedGroups');
    }

    public function testSearchQuery(): void
    {
        $response = $this->getJson('api/v1/search/index', ['q' => 'турин']);

        verify($response)->arrayHasKey('oftenSearched');
        verify($response)->arrayHasKey('categoriesFound');
        verify($response)->arrayHasKey('products');
        verify($response)->arrayHasKey('correction');
    }
}
