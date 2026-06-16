<?php

use app\models\ApiAccessToken;
use app\models\AttributeDefinition;
use app\models\Category;
use app\models\Product;
use app\models\ProductAttribute;
use app\models\ProductCategory;
use app\models\User;

class CatalogApiCest
{
    private ?string $bearerToken = null;
    private int $rootCategoryId = 0;
    private int $childCategoryId = 0;

    public function _before(FunctionalTester $I): void
    {
        $this->truncateCatalogTables();
        $this->seedCatalogData();
        $this->bearerToken = $this->createBearerToken();
    }

    public function listCategories(FunctionalTester $I): void
    {
        $I->sendAjaxGetRequest('/index-test.php/api/v1/categories');
        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson(['meta' => ['total' => 3]]);
    }

    public function categoryTree(FunctionalTester $I): void
    {
        $I->sendAjaxGetRequest('/index-test.php/api/v1/categories/tree');
        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson(['items' => [['slug' => 'divany-i-kresla']]]);
    }

    public function viewCategoryBySlug(FunctionalTester $I): void
    {
        $I->sendAjaxGetRequest('/index-test.php/api/v1/categories/slug/divany-i-kresla');
        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson(['name' => 'Диваны и кресла', 'slug' => 'divany-i-kresla']);
    }

    public function filterCategoriesBySlug(FunctionalTester $I): void
    {
        $I->sendAjaxGetRequest('/index-test.php/api/v1/categories', ['slug' => 'kresla']);
        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson(['items' => [['slug' => 'divany-i-kresla']]]);
    }

    public function filterProductsByCategorySlug(FunctionalTester $I): void
    {
        $I->sendAjaxGetRequest('/index-test.php/api/v1/products', ['category_slug' => 'pryamye']);
        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson(['items' => [['sku' => 'SOFA-BRERA-BLUE']]]);
    }

    public function listProductsWithAttributeFilter(FunctionalTester $I): void
    {
        $I->sendAjaxGetRequest('/index-test.php/api/v1/products', [
            'attrs' => ['color' => 'синий'],
        ]);
        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson(['items' => [['sku' => 'SOFA-BRERA-BLUE']]]);
    }

    public function createCategoryRequiresAuth(FunctionalTester $I): void
    {
        $I->sendAjaxPostRequest('/index-test.php/api/v1/categories', [
            'name' => 'Новая',
            'slug' => 'novaya',
        ]);
        $I->seeResponseCodeIs(401);
    }

    public function createAndDeleteCategory(FunctionalTester $I): void
    {
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->bearerToken);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendAjaxPostRequest('/index-test.php/api/v1/categories', json_encode([
            'name' => 'Садовая мебель',
            'slug' => 'sadovaya-mebel',
            'seo_title' => 'Садовая мебель',
            'seo_h1' => 'Садовая мебель',
        ], JSON_UNESCAPED_UNICODE));

        $I->seeResponseCodeIs(201);
        $I->seeResponseContainsJson(['slug' => 'sadovaya-mebel']);

        $id = (int) json_decode($I->grabResponse(), true)['id'];

        $I->sendAjaxRequest('DELETE', '/index-test.php/api/v1/categories/' . $id);
        $I->seeResponseCodeIs(204);
    }

    public function createProductWithCategories(FunctionalTester $I): void
    {
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->bearerToken);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendAjaxPostRequest('/index-test.php/api/v1/products', json_encode([
            'name' => 'Кресло тестовое',
            'slug' => 'kreslo-testovoe',
            'sku' => 'CHAIR-TEST-1',
            'price' => 15990,
            'stock_qty' => 3,
            'category_ids' => [$this->rootCategoryId, $this->childCategoryId],
            'attributes' => [
                ['code' => 'color', 'value' => 'бежевый'],
                ['code' => 'material', 'value' => 'рогожка'],
                ['code' => 'width', 'value' => 90],
                ['code' => 'is_new', 'value' => false],
            ],
        ], JSON_UNESCAPED_UNICODE));

        $I->seeResponseCodeIs(201);
        $I->seeResponseContainsJson([
            'sku' => 'CHAIR-TEST-1',
            'categories' => [
                ['slug' => 'divany-i-kresla'],
                ['slug' => 'pryamye-divany'],
            ],
        ]);
    }

    private function truncateCatalogTables(): void
    {
        $db = \Yii::$app->db;
        $db->createCommand()->delete('{{%product_attributes}}')->execute();
        $db->createCommand()->delete('{{%product_categories}}')->execute();
        $db->createCommand()->delete('{{%products}}')->execute();
        $db->createCommand()->delete('{{%categories}}')->execute();
        $db->createCommand()->delete('{{%attribute_definitions}}')->execute();
    }

    private function seedCatalogData(): void
    {
        $now = date('Y-m-d H:i:s');

        $category = new Category([
            'name' => 'Диваны и кресла',
            'slug' => 'divany-i-kresla',
            'is_active' => true,
            'sort_order' => 10,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $category->save(false);
        $this->rootCategoryId = (int) $category->id;

        $child = new Category([
            'parent_id' => $category->id,
            'name' => 'Прямые диваны',
            'slug' => 'pryamye-divany',
            'is_active' => true,
            'sort_order' => 10,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $child->save(false);
        $this->childCategoryId = (int) $child->id;

        $other = new Category([
            'name' => 'Кровати',
            'slug' => 'krovati',
            'is_active' => true,
            'sort_order' => 20,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $other->save(false);

        foreach ([
            ['color', 'Цвет', AttributeDefinition::TYPE_STRING],
            ['material', 'Материал', AttributeDefinition::TYPE_STRING],
            ['width', 'Ширина', AttributeDefinition::TYPE_NUMBER],
            ['is_new', 'Новинка', AttributeDefinition::TYPE_BOOL],
        ] as [$code, $name, $type]) {
            $definition = new AttributeDefinition([
                'code' => $code,
                'name' => $name,
                'type' => $type,
                'is_filterable' => true,
                'is_required' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $definition->save(false);
        }

        $product = new Product([
            'name' => 'Диван тест',
            'slug' => 'divan-test',
            'sku' => 'SOFA-BRERA-BLUE',
            'price' => 114990,
            'is_active' => true,
            'stock_qty' => 5,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $product->save(false);

        (new ProductCategory(['product_id' => $product->id, 'category_id' => $category->id]))->save(false);
        (new ProductCategory(['product_id' => $product->id, 'category_id' => $child->id]))->save(false);

        foreach ([
            ['color', 'синий', null, null],
            ['material', 'букле', null, null],
            ['width', null, 220, null],
            ['is_new', null, null, true],
        ] as [$code, $string, $number, $bool]) {
            $attribute = new ProductAttribute([
                'product_id' => $product->id,
                'attribute_code' => $code,
                'value_string' => $string,
                'value_number' => $number,
                'value_bool' => $bool,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $attribute->save(false);
        }
    }

    private function createBearerToken(): string
    {
        $user = User::find()->one();
        if ($user === null) {
            $user = new User([
                'phone' => '79990001122',
                'username' => 'catalog_tester',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $user->save(false);
        }

        $rawToken = 'catalog-test-token-' . bin2hex(random_bytes(16));
        $token = new ApiAccessToken([
            'user_id' => (int) $user->id,
            'token_hash' => hash('sha256', $rawToken),
            'expires_at' => date('Y-m-d H:i:s', time() + 3600),
        ]);
        $token->save(false);

        return $rawToken;
    }
}
