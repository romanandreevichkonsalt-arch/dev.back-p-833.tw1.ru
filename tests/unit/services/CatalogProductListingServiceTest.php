<?php

namespace tests\unit\services;

use app\models\CatalogCategory;
use app\models\CatalogCollection;
use app\models\CatalogColor;
use app\models\CatalogDirection;
use app\models\CatalogFabricCollection;
use app\models\CatalogFabricColor;
use app\models\CatalogModel;
use app\models\CatalogProduct;
use app\models\CatalogSubcategory;
use app\models\SearchCatalogPriorityModel;
use app\services\catalog\CatalogProductListingService;
use Codeception\Test\Unit;
use Yii;

class CatalogProductListingServiceTest extends Unit
{
    private CatalogProductListingService $listing;
    private string $prefix;

    protected function _before(): void
    {
        $this->listing = Yii::$container->get(CatalogProductListingService::class);
        $this->prefix = 'rr' . substr(uniqid(), -8);
    }

    public function testLibraryProductsReturnsOneSkuPerCollectionWithSameSort(): void
    {
        $fixture = $this->createFixture();

        $response = $this->listing->getLibraryProducts([
            'subcategory' => $fixture['subcategorySlug'],
            'perPage' => 24,
        ]);

        verify($response['meta']['total'])->equals(4);
        verify($response['meta']['sort'])->equals('default');
        verify(count($response['items']))->equals(4);

        $collectionSlugs = array_map(
            static fn (array $item): string => (string)($item['collection']['slug'] ?? ''),
            $response['items']
        );
        verify($collectionSlugs)->equals([
            $this->prefix . '-adriano',
            $this->prefix . '-apollo',
            $this->prefix . '-athena',
            $this->prefix . '-venice',
        ]);

        $expectedKeys = [
            'direction',
            'category',
            'subcategory',
            'collection',
            'image',
            'polygons_3d',
            'file_3d_url',
            'badge',
        ];
        foreach ($response['items'] as $item) {
            verify(array_keys($item))->equals($expectedKeys);
        }
    }

    public function testLibraryProductsCategoryFilter(): void
    {
        $fixture = $this->createFixture();
        $categorySlug = $this->prefix . '-cat';

        $filtered = $this->listing->getLibraryProducts([
            'category' => $categorySlug,
            'perPage' => 100,
        ]);
        verify($filtered['meta']['total'])->equals(4);

        $otherCategory = $this->createCategory($this->prefix . '-other-cat');
        $otherSub = $this->createSubcategory($otherCategory, $this->prefix . '-other-sub');
        $direction = CatalogDirection::find()->where(['slug' => $this->prefix . '-dir'])->one();
        verify($direction)->notNull();
        $collection = $this->createCatalogCollection($direction, $this->prefix . '-solo', 99);
        $model = $this->createModel($collection, $otherCategory, $otherSub, 'solo', 99);
        $fabric = $this->createFabricCollection($this->prefix . '-solo-fab');
        $color = $this->createFabricColor($fabric, 'c1', 'Solo', 1);
        $this->createSku($model, $color, $this->prefix . '-solo-c1', 1);

        $onlyOther = $this->listing->getLibraryProducts([
            'category' => $otherCategory->slug,
            'perPage' => 100,
        ]);
        verify($onlyOther['meta']['total'])->equals(1);
        verify($onlyOther['items'][0]['collection']['slug'])->equals($this->prefix . '-solo');

        $originalSub = $this->listing->getLibraryProducts([
            'subcategory' => $fixture['subcategorySlug'],
            'perPage' => 100,
        ]);
        verify($originalSub['meta']['total'])->equals(4);
    }

    public function testDefaultSortInterleavesModelsAndHidesCustom(): void
    {
        $fixture = $this->createFixture();

        $response = $this->listing->getProducts([
            'subcategory' => $fixture['subcategorySlug'],
            'perPage' => 8,
            'page' => 1,
        ]);

        verify($response['meta']['total'])->equals(8);
        verify($response['meta']['sort'])->equals('default');
        verify($response['meta']['layout'])->equals('flat');
        verify(count($response['items']))->equals(8);

        $slugs = array_column($response['items'], 'slug');
        verify($slugs)->equals($fixture['expectedSlugs']);
        verify(in_array($fixture['customSlug'], $slugs, true))->false();

        $page2 = $this->listing->getProducts([
            'subcategory' => $fixture['subcategorySlug'],
            'perPage' => 4,
            'page' => 2,
        ]);
        verify(array_column($page2['items'], 'slug'))->equals(array_slice($fixture['expectedSlugs'], 4, 4));
    }

    public function testRepeatedColorQueryParamsUnionResults(): void
    {
        $fixture = $this->createFixture();
        $color1Slug = $this->prefix . '-fab-c1';
        $color2Slug = $this->prefix . '-fab-c2';
        $subcategorySlug = $fixture['subcategorySlug'];

        $singleColor1 = $this->listing->getProducts([
            'subcategory' => $subcategorySlug,
            'color' => [$color1Slug],
            'perPage' => 100,
        ]);
        $singleColor2 = $this->listing->getProducts([
            'subcategory' => $subcategorySlug,
            'color' => [$color2Slug],
            'perPage' => 100,
        ]);

        $bothColors = $this->runWithQueryString(
            'subcategory=' . rawurlencode($subcategorySlug)
                . '&color=' . rawurlencode($color1Slug)
                . '&color=' . rawurlencode($color2Slug),
            [
                'subcategory' => $subcategorySlug,
                'color' => $color2Slug,
                'perPage' => 100,
            ],
            fn (): array => $this->listing->getProducts([
                'subcategory' => $subcategorySlug,
                'color' => $color2Slug,
                'perPage' => 100,
            ])
        );

        verify($singleColor1['meta']['total'])->equals(4);
        verify($singleColor2['meta']['total'])->equals(4);
        verify($bothColors['meta']['total'])->equals(8);
    }

    /**
     * @param array<string, mixed> $queryParams
     * @param callable(): array<string, mixed> $action
     * @return array<string, mixed>
     */
    private function runWithQueryString(string $queryString, array $queryParams, callable $action): array
    {
        $backupGet = $_GET;
        $backupQueryString = $_SERVER['QUERY_STRING'] ?? null;

        $_GET = $queryParams;
        $_SERVER['QUERY_STRING'] = $queryString;
        Yii::$app->set('request', Yii::createObject(['class' => \yii\web\Request::class]));

        try {
            return $action();
        } finally {
            $_GET = $backupGet;
            if ($backupQueryString === null) {
                unset($_SERVER['QUERY_STRING']);
            } else {
                $_SERVER['QUERY_STRING'] = $backupQueryString;
            }
            Yii::$app->set('request', Yii::createObject(['class' => \yii\web\Request::class]));
        }
    }

    public function testCollectionGroupsReturnsModelLineSections(): void
    {
        $fixture = $this->createFixture();

        $response = $this->listing->getProducts([
            'subcategory' => $fixture['subcategorySlug'],
            'collection' => 'true',
            'perPage' => 10,
            'itemsPerGroup' => 3,
        ]);

        verify($response['meta']['layout'])->equals('collectionGroups');
        verify($response['meta']['itemsPerGroup'])->equals(3);
        verify($response['meta']['total'])->equals(4);
        verify(count($response['items']))->equals(4);

        $firstGroup = $response['items'][0];
        verify($firstGroup)->arrayHasKey('slug');
        verify($firstGroup)->arrayHasKey('title');
        verify($firstGroup)->arrayHasKey('total');
        verify($firstGroup)->arrayHasKey('items');
        verify($firstGroup['total'])->equals(2);
        verify(count($firstGroup['items']))->equals(2);
        verify(array_column($firstGroup['items'], 'slug'))->equals([
            $this->prefix . '-adriano-c1',
            $this->prefix . '-adriano-c2',
        ]);
    }

    public function testCollectionGroupsRespectsItemsPerGroupAndModelLineScope(): void
    {
        $fixture = $this->createFixture();

        $singleLine = $this->listing->getProducts([
            'subcategory' => $fixture['subcategorySlug'],
            'modelLine' => $this->prefix . '-apollo',
            'collection' => 'true',
            'itemsPerGroup' => 1,
        ]);

        verify($singleLine['meta']['total'])->equals(1);
        verify(count($singleLine['items']))->equals(1);
        verify($singleLine['items'][0]['slug'])->equals($this->prefix . '-apollo');
        verify(count($singleLine['items'][0]['items']))->equals(1);
        verify($singleLine['items'][0]['items'][0]['slug'])->equals($this->prefix . '-apollo-c1');
    }

    public function testCollectionGroupsHideEmptyModelLinesAfterFilter(): void
    {
        $fixture = $this->createFixture();

        $response = $this->listing->getProducts([
            'subcategory' => $fixture['subcategorySlug'],
            'collection' => 'true',
            'priceMin' => 400,
            'perPage' => 10,
        ]);

        verify($response['meta']['total'])->equals(1);
        verify(count($response['items']))->equals(1);
        verify($response['items'][0]['slug'])->equals($this->prefix . '-venice');
    }

    public function testListingPriorityPrependsOnPageOneWhenDirectionInScope(): void
    {
        if (\Yii::$app->db->schema->getTableSchema(SearchCatalogPriorityModel::tableName(), true) === null) {
            $this->markTestSkipped('search_catalog_priority_models is not migrated in test DB.');
        }

        $fixture = $this->createFixture();
        $direction = CatalogDirection::find()->where(['slug' => $this->prefix . '-dir'])->one();
        verify($direction)->notNull();

        $veniceProduct = CatalogProduct::find()->where(['slug' => $this->prefix . '-venice-c2'])->one();
        $adrianoProduct = CatalogProduct::find()->where(['slug' => $this->prefix . '-adriano-c1'])->one();
        verify($veniceProduct)->notNull();
        verify($adrianoProduct)->notNull();

        $priorityRows = [
            new SearchCatalogPriorityModel([
                'direction_id' => (int)$direction->id,
                'catalog_model_id' => (int)$veniceProduct->model_id,
                'sample_catalog_product_id' => (int)$veniceProduct->id,
                'sort_order' => 1,
            ]),
            new SearchCatalogPriorityModel([
                'direction_id' => (int)$direction->id,
                'catalog_model_id' => (int)$adrianoProduct->model_id,
                'sample_catalog_product_id' => (int)$adrianoProduct->id,
                'sort_order' => 2,
            ]),
        ];
        foreach ($priorityRows as $row) {
            verify($row->save(false))->true();
        }

        try {
            $response = $this->listing->getProducts([
                'direction' => $fixture['directionSlug'],
                'subcategory' => $fixture['subcategorySlug'],
                'perPage' => 8,
                'page' => 1,
            ]);

            $slugs = array_column($response['items'], 'slug');
            verify($slugs[0])->equals($this->prefix . '-venice-c2');
            verify($slugs[1])->equals($this->prefix . '-adriano-c1');
            verify($response['items'][0]['listingPriorityOrder'] ?? null)->equals(1);
            verify($response['items'][1]['listingPriorityOrder'] ?? null)->equals(2);
            verify(count($slugs))->equals(8);

            $page2 = $this->listing->getProducts([
                'direction' => $fixture['directionSlug'],
                'subcategory' => $fixture['subcategorySlug'],
                'perPage' => 4,
                'page' => 2,
            ]);
            verify(in_array($this->prefix . '-venice-c2', array_column($page2['items'], 'slug'), true))->false();
        } finally {
            SearchCatalogPriorityModel::deleteAll([
                'direction_id' => (int)$direction->id,
                'catalog_model_id' => [
                    (int)$veniceProduct->model_id,
                    (int)$adrianoProduct->model_id,
                ],
            ]);
        }
    }

    public function testPopularSortMatchesDefaultRoundRobinOrder(): void
    {
        $fixture = $this->createFixture();

        $default = $this->listing->getProducts([
            'subcategory' => $fixture['subcategorySlug'],
            'sort' => 'default',
            'perPage' => 8,
        ]);
        $popular = $this->listing->getProducts([
            'subcategory' => $fixture['subcategorySlug'],
            'sort' => 'popular',
            'perPage' => 8,
        ]);

        verify(array_column($popular['items'], 'slug'))
            ->equals(array_column($default['items'], 'slug'));
    }

    public function testPriceSortInterleavesModelsByPrice(): void
    {
        $fixture = $this->createFixture();

        $response = $this->listing->getProducts([
            'subcategory' => $fixture['subcategorySlug'],
            'sort' => 'price_asc',
            'perPage' => 8,
        ]);

        $slugs = array_column($response['items'], 'slug');
        verify($slugs)->equals([
            $this->prefix . '-adriano-c1',
            $this->prefix . '-apollo-c2',
            $this->prefix . '-athena-c1',
            $this->prefix . '-venice-c2',
            $this->prefix . '-adriano-c2',
            $this->prefix . '-apollo-c1',
            $this->prefix . '-athena-c2',
            $this->prefix . '-venice-c1',
        ]);
        verify(in_array($fixture['customSlug'], $slugs, true))->false();
    }

    /**
     * @return array{directionSlug: string, subcategorySlug: string, expectedSlugs: list<string>, customSlug: string}
     */
    private function createFixture(): array
    {
        $direction = $this->createDirection($this->prefix . '-dir');
        $category = $this->createCategory($this->prefix . '-cat');
        $sub = $this->createSubcategory($category, $this->prefix . '-sub');
        $fabric = $this->createFabricCollection($this->prefix . '-fab');
        $color1 = $this->createFabricColor($fabric, 'c1', 'Цвет1', 1);
        $color2 = $this->createFabricColor($fabric, 'c2', 'Цвет2', 2);

        $names = ['adriano', 'apollo', 'athena', 'venice'];
        $models = [];
        foreach ($names as $index => $name) {
            $collection = $this->createCatalogCollection($direction, $this->prefix . '-' . $name, $index + 1);
            $models[$name] = $this->createModel($collection, $category, $sub, $name, $index + 1);
        }

        foreach ([1, 2] as $round) {
            foreach ($names as $name) {
                $color = $round === 1 ? $color1 : $color2;
                $slug = $this->prefix . '-' . $name . '-c' . $round;
                $this->createSku($models[$name], $color, $slug, $round);
            }
        }

        $expected = [
            $this->prefix . '-adriano-c1',
            $this->prefix . '-apollo-c2',
            $this->prefix . '-athena-c1',
            $this->prefix . '-venice-c2',
            $this->prefix . '-adriano-c2',
            $this->prefix . '-apollo-c1',
            $this->prefix . '-athena-c2',
            $this->prefix . '-venice-c1',
        ];

        $customSlug = $this->prefix . '-adriano-custom';
        $custom = new CatalogProduct([
            'slug' => $customSlug,
            'title' => 'Адриано кастом',
            'href' => '/catalog/' . $customSlug,
            'is_active' => true,
            'is_custom' => true,
            'model_id' => $models['adriano']->id,
            'collection_id' => $models['adriano']->collection_id,
            'subcategory_id' => $sub->id,
            'fabric_color_id' => null,
            'sort_order' => 1,
            'price_amount' => 100,
        ]);
        verify($custom->save(false))->true();

        return [
            'directionSlug' => $direction->slug,
            'subcategorySlug' => $sub->slug,
            'expectedSlugs' => $expected,
            'customSlug' => $customSlug,
        ];
    }

    private function createSku(
        CatalogModel $model,
        CatalogFabricColor $color,
        string $slug,
        int $round
    ): CatalogProduct
    {
        $product = new CatalogProduct([
            'slug' => $slug,
            'title' => $slug,
            'href' => '/catalog/' . $slug,
            'is_active' => true,
            'is_custom' => false,
            'model_id' => $model->id,
            'collection_id' => $model->collection_id,
            'subcategory_id' => $model->subcategory_id,
            'fabric_color_id' => $color->id,
            'sort_order' => $model->sort_order,
            'price_amount' => (int)$model->sort_order * 100 + $round,
        ]);
        verify($product->save(false))->true();

        return $product;
    }

    private function createModel(
        CatalogCollection $collection,
        CatalogCategory $category,
        CatalogSubcategory $sub,
        string $name,
        int $sortOrder
    ): CatalogModel {
        $model = new CatalogModel([
            'collection_id' => $collection->id,
            'category_id' => $category->id,
            'subcategory_id' => $sub->id,
            'slug' => $this->prefix . '-m-' . $name,
            'title' => $name,
            'is_active' => true,
            'sort_order' => $sortOrder,
        ]);
        verify($model->save(false))->true();

        return $model;
    }

    private function createDirection(string $slug): CatalogDirection
    {
        $direction = new CatalogDirection([
            'slug' => $slug,
            'label' => $slug,
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $direction->save(false);

        return $direction;
    }

    private function createCategory(string $slug): CatalogCategory
    {
        $category = new CatalogCategory([
            'slug' => $slug,
            'label' => $slug,
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $category->save(false);

        return $category;
    }

    private function createSubcategory(CatalogCategory $category, string $slug): CatalogSubcategory
    {
        $sub = new CatalogSubcategory([
            'category_id' => $category->id,
            'slug' => $slug,
            'label' => $slug,
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $sub->save(false);

        return $sub;
    }

    private function createCatalogCollection(CatalogDirection $direction, string $slug, int $sortOrder): CatalogCollection
    {
        $collection = new CatalogCollection([
            'direction_id' => $direction->id,
            'slug' => $slug,
            'name' => $slug,
            'label' => 'Коллекция',
            'title' => $slug,
            'href' => '/catalog/' . $slug,
            'sort_order' => $sortOrder,
            'is_active' => true,
        ]);
        $collection->save(false);

        return $collection;
    }

    private function createFabricCollection(string $slug): CatalogFabricCollection
    {
        $fabric = new CatalogFabricCollection([
            'slug' => $slug,
            'name' => $slug,
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $fabric->save(false);

        return $fabric;
    }

    private function createFabricColor(
        CatalogFabricCollection $fabric,
        string $suffix,
        string $label,
        int $sortOrder
    ): CatalogFabricColor {
        $colorSlug = $fabric->slug . '-' . $suffix;
        $catalogColor = new CatalogColor([
            'slug' => $colorSlug,
            'label' => $label,
            'sort_order' => $sortOrder,
            'is_active' => true,
        ]);
        $catalogColor->save(false);

        $link = new CatalogFabricColor([
            'fabric_collection_id' => $fabric->id,
            'color_id' => $catalogColor->id,
            'design_code' => $label,
            'sort_order' => $sortOrder,
            'is_active' => true,
        ]);
        $link->save(false);

        return $link;
    }
}
