<?php

namespace app\commands;

use app\models\AdminUser;
use app\models\CatalogBadge;
use app\models\CatalogCategory;
use app\models\CatalogCollection;
use app\models\CatalogColor;
use app\models\CatalogDirection;
use app\models\CatalogFabricCollection;
use app\models\CatalogFabricColor;
use app\models\CatalogModel;
use app\models\CatalogProduct;
use app\models\CatalogLayout;
use app\models\CatalogPriceCategory;
use app\models\CatalogSubcategory;
use app\models\ContentBlock;
use app\models\ContentPage;
use app\models\Lead;
use app\models\Order;
use app\models\OrderItem;
use app\models\OrderStatusLog;
use app\models\SearchCategory;
use app\models\SearchFrequentQuery;
use app\models\SearchRecommendedProduct;
use app\models\User;
use app\models\UserProfile;
use app\services\catalog\CatalogModelProductSyncService;
use app\services\content\BlockTypeRegistry;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

class SeedController extends Controller
{
    public function actionIndex(): int
    {
        $this->stdout("Seeding database...\n", Console::FG_YELLOW);

        $this->seedUsers();
        $this->seedAdminUsers();
        $this->seedLeads();
        $this->seedOrders();
        $this->seedCatalog();
        $this->seedCatalogV2();
        $this->seedPages();
        $this->seedSearch();

        $this->stdout("Done.\n", Console::FG_GREEN);

        return ExitCode::OK;
    }

    public function actionTest(): int
    {
        $this->stdout("Seeding test fixtures...\n", Console::FG_YELLOW);

        $this->truncateTransactionalTables();
        $this->seedUsers();
        $this->seedAdminUsers();
        $this->seedLeads();
        $this->seedOrders();
        $this->seedCatalog();

        $this->stdout("Test fixtures ready.\n", Console::FG_GREEN);

        return ExitCode::OK;
    }

    public function actionCatalog(): int
    {
        $this->stdout("Importing catalog from JSON...\n", Console::FG_YELLOW);
        $this->seedCatalog(true);
        $this->seedCatalogV2(true);
        $this->stdout("Catalog import done.\n", Console::FG_GREEN);

        return ExitCode::OK;
    }

    public function actionCatalogV2(): int
    {
        $this->stdout("Seeding catalog v2 demo (models, fabrics)...\n", Console::FG_YELLOW);
        $this->seedCatalogV2(true);
        $this->stdout("Catalog v2 seed done.\n", Console::FG_GREEN);

        return ExitCode::OK;
    }

    public function actionPages(): int
    {
        $this->stdout("Importing pages from JSON...\n", Console::FG_YELLOW);
        $this->seedPages(true);
        $this->stdout("Pages import done.\n", Console::FG_GREEN);

        return ExitCode::OK;
    }

    public function actionPagesMissing(): int
    {
        $this->stdout("Importing missing pages from JSON...\n", Console::FG_YELLOW);
        $added = $this->seedMissingPagesFromJson();
        $this->stdout(sprintf("Added %d page(s).\n", $added), Console::FG_GREEN);

        return ExitCode::OK;
    }

    public function actionSearch(): int
    {
        $this->stdout("Importing search settings from JSON...\n", Console::FG_YELLOW);
        $this->seedSearch(true);
        $this->stdout("Search import done.\n", Console::FG_GREEN);

        return ExitCode::OK;
    }

    public function actionSettings(): int
    {
        $this->stdout("Seeding settings references...\n", Console::FG_YELLOW);
        $this->seedSettingsReferences();
        $this->stdout("Settings references ready.\n", Console::FG_GREEN);

        return ExitCode::OK;
    }

    private function truncateTransactionalTables(): void
    {
        $db = Yii::$app->db;
        $db->createCommand('SET FOREIGN_KEY_CHECKS=0')->execute();
        foreach (['{{%api_access_tokens}}', '{{%sms_codes}}', '{{%leads}}', '{{%user_profiles}}', '{{%external_identities}}', '{{%users}}'] as $table) {
            $db->createCommand()->truncateTable($table)->execute();
        }
        $db->createCommand('SET FOREIGN_KEY_CHECKS=1')->execute();
    }

    private function seedUsers(): void
    {
        $users = [
            [
                'phone' => '79894232000',
                'username' => 'Иван',
                'profile' => [
                    'first_name' => 'Иван',
                    'last_name' => 'Иванов',
                    'display_name' => 'Иван',
                    'email' => 'ivan@example.com',
                    'avatar_url' => null,
                ],
            ],
            [
                'phone' => '79998886644',
                'username' => 'Мария',
                'profile' => [
                    'first_name' => 'Мария',
                    'last_name' => 'Петрова',
                    'display_name' => 'Мария',
                    'email' => 'maria@example.com',
                    'avatar_url' => null,
                ],
            ],
        ];

        foreach ($users as $data) {
            $user = User::findByPhone($data['phone']);
            if ($user === null) {
                $user = new User([
                    'phone' => $data['phone'],
                    'username' => $data['username'],
                ]);
                $user->save(false);
            }

            $profile = UserProfile::find()->where(['user_id' => (int)$user->id])->one();
            if ($profile === null) {
                $profile = new UserProfile(array_merge(['user_id' => (int)$user->id], $data['profile']));
                $profile->save(false);
            }
        }

        $this->stdout("  users: " . User::find()->count() . "\n");
    }

    private function seedLeads(): void
    {
        if (Lead::find()->exists()) {
            $this->stdout("  leads: already seeded\n");

            return;
        }

        $leads = [
            [
                'type' => Lead::TYPE_CONTACTS,
                'name' => 'Иван Иванов',
                'email' => 'ivan@example.com',
                'phone' => '+79894232000',
                'comment' => 'Интересует диван из коллекции А+',
                'consent' => true,
            ],
            [
                'type' => Lead::TYPE_FAQ,
                'name' => 'Анна Смирнова',
                'email' => 'anna@example.com',
                'phone' => '+79998886644',
                'comment' => 'Вопрос по доставке',
                'consent' => true,
            ],
            [
                'type' => Lead::TYPE_PARTNERS,
                'name' => 'Пётр Козлов',
                'email' => 'petr@example.com',
                'phone' => '+79181234567',
                'comment' => 'Хочу открыть салон',
                'consent' => true,
            ],
            [
                'type' => Lead::TYPE_DESIGNERS,
                'name' => 'Елена Дизайнова',
                'email' => 'elena@example.com',
                'phone' => '+79185551234',
                'studio' => 'Studio Light',
                'portfolio' => 'https://example.com/portfolio',
                'city' => 'Ростов-на-Дону',
                'consent' => true,
            ],
        ];

        foreach ($leads as $data) {
            $lead = new Lead($data);
            $lead->save(false);
        }

        $this->stdout('  leads: ' . Lead::find()->count() . "\n");
    }

    private function seedAdminUsers(): void
    {
        if (AdminUser::find()->exists()) {
            $this->stdout("  admin_users: already seeded\n");

            return;
        }

        $user = new AdminUser([
            'username' => 'admin',
            'name' => 'Администратор',
            'email' => 'admin@example.com',
            'role' => AdminUser::ROLE_ADMIN,
            'is_active' => true,
        ]);
        $user->generateAuthKey();
        $user->setPassword('admin123');
        $user->save(false);

        $this->stdout("  admin_users: 1 (login: admin / admin123)\n");
    }

    private function seedOrders(): void
    {
        if (Order::find()->exists()) {
            $this->stdout("  orders: already seeded\n");

            return;
        }

        $order = new Order([
            'number' => Order::generateNumber(),
            'customer_name' => 'Иван Иванов',
            'customer_phone' => '+79894232000',
            'customer_email' => 'ivan@example.com',
            'delivery_address' => 'г. Ростов-на-Дону, ул. Пушкинская, 18',
            'comment' => 'Диван из коллекции А+',
            'status' => Order::STATUS_CONFIRMED,
            'total_amount' => 350900,
        ]);
        $order->save(false);

        $items = [
            ['product_title' => 'Диван Стенли', 'product_sku' => 'STN-001', 'quantity' => 1, 'unit_price' => 350900],
        ];

        foreach ($items as $row) {
            $item = new OrderItem([
                'order_id' => (int)$order->id,
                'product_title' => $row['product_title'],
                'product_sku' => $row['product_sku'],
                'quantity' => $row['quantity'],
                'unit_price' => $row['unit_price'],
                'line_total' => $row['unit_price'] * $row['quantity'],
            ]);
            $item->save(false);
        }

        $log = new OrderStatusLog([
            'order_id' => (int)$order->id,
            'old_status' => Order::STATUS_NEW,
            'new_status' => Order::STATUS_CONFIRMED,
            'comment' => 'Подтверждён менеджером',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $log->save(false);

        $this->stdout('  orders: 1' . "\n");
    }

    private function seedCatalog(bool $force = false): void
    {
        if (CatalogDirection::find()->exists() && !$force) {
            $this->stdout("  catalog: already seeded\n");

            return;
        }

        if ($force) {
            $db = Yii::$app->db;
            $db->createCommand('SET FOREIGN_KEY_CHECKS=0')->execute();
            foreach ([
                '{{%catalog_color_images}}',
                '{{%catalog_fabric_collection_colors}}',
                '{{%catalog_colors}}',
                '{{%catalog_model_images}}',
                '{{%catalog_model_fabric_collections}}',
                '{{%catalog_model_prices}}',
                '{{%catalog_models}}',
                '{{%catalog_fabric_collections}}',
                '{{%catalog_products}}',
                '{{%catalog_badges}}',
                '{{%catalog_layouts}}',
                '{{%catalog_collection_images}}',
                '{{%catalog_collections}}',
                '{{%catalog_subcategories}}',
                '{{%catalog_categories}}',
                '{{%catalog_directions}}',
            ] as $table) {
                $db->createCommand()->truncateTable($table)->execute();
            }
            $db->createCommand('SET FOREIGN_KEY_CHECKS=1')->execute();
        }

        $defaultDirectionId = null;
        $menuPath = Yii::getAlias('@app/data/content/catalog/menu.json');
        $menu = json_decode((string)file_get_contents($menuPath), true);
        if (!is_array($menu)) {
            $this->stderr("  catalog: invalid menu.json\n", Console::FG_RED);

            return;
        }

        $subcategoryMap = $this->seedGlobalCatalogCategories();
        $sortDirection = 0;
        foreach ($menu['groups'] ?? [] as $groupData) {
            $direction = new CatalogDirection([
                'slug' => $groupData['id'],
                'label' => $groupData['label'],
                'sort_order' => $sortDirection++,
                'is_active' => true,
            ]);
            $direction->save(false);
            if ($defaultDirectionId === null) {
                $defaultDirectionId = (int)$direction->id;
            }
        }

        $collectionMap = [];
        $sortCollection = 0;
        foreach ($menu['collections'] ?? [] as $collectionData) {
            $name = trim((string)($collectionData['label'] ?? '') . ' ' . (string)($collectionData['title'] ?? ''));
            if ($name === '') {
                $name = (string)($collectionData['title'] ?? $collectionData['id']);
            }
            $collection = new CatalogCollection([
                'direction_id' => $defaultDirectionId,
                'slug' => $collectionData['id'],
                'name' => $name,
                'label' => $collectionData['label'] ?? '',
                'title' => $collectionData['title'] ?? '',
                'title_uppercase' => (bool)($collectionData['titleUppercase'] ?? false),
                'description' => $collectionData['description'] ?? null,
                'href' => $collectionData['href'] ?? '',
                'cta_label' => $collectionData['ctaLabel'] ?? null,
                'image_position' => $collectionData['imagePosition'] ?? null,
                'sort_order' => $sortCollection++,
                'is_active' => true,
            ]);
            $collection->save(false);
            $collectionMap[$collection->slug] = (int)$collection->id;
        }

        $this->seedDefaultLayouts();
        $productsBySlug = [];
        $productsDir = Yii::getAlias('@app/data/content/catalog/products');
        foreach (glob($productsDir . '/*.json') ?: [] as $file) {
            $subSlug = basename($file, '.json');
            $data = json_decode((string)file_get_contents($file), true);
            foreach ($data['items'] ?? [] as $index => $item) {
                $productsBySlug[$item['id']] = $this->buildProductFromMenuItem($item, $subcategoryMap[$subSlug] ?? null, $index);
            }
        }

        $searchPath = Yii::getAlias('@app/data/content/search/products-index.json');
        $searchData = json_decode((string)file_get_contents($searchPath), true);
        foreach ($searchData['items'] ?? [] as $index => $item) {
            $slug = $item['id'];
            if (!isset($productsBySlug[$slug])) {
                $productsBySlug[$slug] = [
                    'slug' => $slug,
                    'title' => $item['title'] ?? $slug,
                    'subcategory_id' => $subcategoryMap['straight'] ?? null,
                    'collection_id' => $this->findCollectionIdByName($item['collection'] ?? null),
                    'price_display' => $item['price'] ?? null,
                    'href' => $item['to'] ?? '',
                    'image_position' => $item['imagePosition'] ?? null,
                    'badge_id' => null,
                    'sort_order' => $index,
                    'is_active' => true,
                ];
            } else {
                $productsBySlug[$slug]['collection_id'] = $this->findCollectionIdByName($item['collection'] ?? null) ?? $productsBySlug[$slug]['collection_id'] ?? null;
                $productsBySlug[$slug]['price_display'] = $item['price'] ?? $productsBySlug[$slug]['price_display'];
                $productsBySlug[$slug]['href'] = $item['to'] ?? $productsBySlug[$slug]['href'];
            }
        }

        foreach ($productsBySlug as $row) {
            $product = new CatalogProduct($row);
            $product->save(false);
        }

        $this->stdout(sprintf(
            "  catalog: %d directions, %d products\n",
            CatalogDirection::find()->count(),
            CatalogProduct::find()->count()
        ));

        $this->seedCatalogV2();
    }

    private function seedCatalogV2(bool $force = false): void
    {
        if (CatalogModel::find()->exists() && !$force) {
            $this->stdout("  catalog v2: already seeded\n");

            return;
        }

        if ($force) {
            $db = Yii::$app->db;
            $db->createCommand('SET FOREIGN_KEY_CHECKS=0')->execute();
            foreach ([
                '{{%catalog_color_images}}',
                '{{%catalog_fabric_collection_colors}}',
                '{{%catalog_colors}}',
                '{{%catalog_model_images}}',
                '{{%catalog_model_fabric_collections}}',
                '{{%catalog_model_prices}}',
                '{{%catalog_models}}',
                '{{%catalog_fabric_collections}}',
            ] as $table) {
                $db->createCommand()->delete($table)->execute();
            }
            CatalogProduct::updateAll(['model_id' => null, 'fabric_color_id' => null]);
            $db->createCommand('SET FOREIGN_KEY_CHECKS=1')->execute();
        }

        $collection = CatalogCollection::find()->orderBy(['id' => SORT_ASC])->one();
        if ($collection === null) {
            $this->stderr("  catalog v2: no catalog collections — run seed/catalog first\n", Console::FG_RED);

            return;
        }

        $category = CatalogCategory::find()
            ->where(['slug' => 'sofa'])
            ->one();
        $subcategory = CatalogSubcategory::find()->orderBy(['id' => SORT_ASC])->one();
        if ($category === null) {
            $this->stderr("  catalog v2: no catalog collections — run seed/catalog first\n", Console::FG_RED);

            return;
        }

        $fabric = new CatalogFabricCollection([
            'slug' => 'demo-fabric',
            'name' => 'Демо фактура',
            'description' => 'Демонстрационная фактура для catalog v2',
            'meter_price_display' => '720 ₽/м',
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $fabric->save(false);

        $colorDefs = [
            ['slug' => 'gray', 'label' => 'Серый', 'hex' => '#8a8a8a'],
            ['slug' => 'beige', 'label' => 'Бежевый', 'hex' => '#d4c4a8'],
            ['slug' => 'brown', 'label' => 'Коричневый', 'hex' => '#6b5344'],
            ['slug' => 'olive', 'label' => 'Оливковый', 'hex' => '#7a8b6e'],
        ];

        foreach ($colorDefs as $index => $def) {
            $catalogColor = CatalogColor::findOne(['slug' => $def['slug']]);
            if ($catalogColor === null) {
                $catalogColor = new CatalogColor([
                    'slug' => $def['slug'],
                    'label' => $def['label'],
                    'hex_color' => $def['hex'] ?? null,
                    'sort_order' => $index,
                    'is_active' => true,
                ]);
                $catalogColor->save(false);
            }

            $link = new CatalogFabricColor([
                'fabric_collection_id' => $fabric->id,
                'color_id' => $catalogColor->id,
                'design_code' => $def['label'],
                'sort_order' => $index,
                'is_active' => true,
            ]);
            $link->save(false);
        }

        $model = new CatalogModel([
            'collection_id' => $collection->id,
            'category_id' => $category->id,
            'subcategory_id' => $subcategory?->id,
            'slug' => 'demo-straight-sofa',
            'title' => 'Диван демо (прямой)',
            'subtitle' => 'Catalog v2',
            'description' => 'Демонстрационная модель с автогенерацией товаров по цветам фактуры.',
            'overall_size' => '220×85×95',
            'seat_depth' => '60',
            'frame' => 'Массив дерева',
            'upholstery' => 'Ткань (демо фактура)',
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $model->save(false);

        $prices = [];
        foreach (CatalogPriceCategory::find()->orderBy(['number' => SORT_ASC])->all() as $priceCategory) {
            $prices[$priceCategory->id] = number_format(90000 + (int)$priceCategory->number * 10000, 0, '', ' ') . ' ₽';
        }
        $model->syncPrices($prices);
        $model->syncFabricCollectionLinks([$fabric->id]);

        Yii::$container->get(CatalogModelProductSyncService::class)->syncForModel($model);

        $this->stdout(sprintf(
            "  catalog v2: 1 fabric, %d colors, 1 model, %d generated products\n",
            CatalogFabricColor::find()->count(),
            CatalogProduct::find()->where(['model_id' => $model->id])->count()
        ));
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function buildProductFromMenuItem(array $item, ?int $subcategoryId, int $sortOrder): array
    {
        $badge = $item['badge'] ?? null;

        return [
            'slug' => $item['id'],
            'title' => $item['title'] ?? $item['id'],
            'subtitle' => $item['subtitle'] ?? null,
            'description' => $item['description'] ?? null,
            'subcategory_id' => $subcategoryId,
            'collection_id' => $this->findCollectionIdByName($item['collection'] ?? null),
            'price_display' => null,
            'href' => $item['href'] ?? '',
            'image_position' => $item['imagePosition'] ?? null,
            'badge_id' => is_array($badge) ? $this->ensureBadge($badge) : null,
            'layout_id' => $this->ensureLayout($item['layout'] ?? null),
            'sort_order' => $sortOrder,
            'is_active' => true,
        ];
    }

    private function seedDefaultLayouts(): void
    {
        if (CatalogLayout::find()->exists()) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        foreach ([
            ['featured', 'Featured'],
            ['stacked', 'Stacked'],
            ['compact', 'Compact'],
        ] as $index => [$slug, $label]) {
            $layout = new CatalogLayout([
                'slug' => $slug,
                'label' => $label,
                'sort_order' => $index,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $layout->save(false);
        }
    }

    private function seedSettingsReferences(): void
    {
        $direction = CatalogDirection::find()->orderBy(['sort_order' => SORT_ASC])->one();
        if ($direction === null) {
            $this->stderr("  settings: нет направлений — выполните yii seed/catalog\n", Console::FG_RED);

            return;
        }

        $collections = [
            [
                'slug' => 'test-nova',
                'name' => 'Nova (тест)',
                'label' => 'Коллекция',
                'title' => 'Nova',
                'href' => '/catalog/nova',
                'sort_order' => 100,
            ],
            [
                'slug' => 'test-luna',
                'name' => 'Luna (тест)',
                'label' => 'Коллекция',
                'title' => 'Luna',
                'href' => '/catalog/luna',
                'sort_order' => 101,
            ],
        ];

        foreach ($collections as $data) {
            if (CatalogCollection::find()->where(['slug' => $data['slug']])->exists()) {
                continue;
            }

            $collection = new CatalogCollection(array_merge($data, [
                'direction_id' => (int)$direction->id,
                'is_active' => true,
            ]));
            $collection->save(false);
            $this->stdout('  collection: ' . $data['slug'] . "\n");
        }
    }

    /**
     * @return array<string, int>
     */
    private function seedGlobalCatalogCategories(): array
    {
        $defs = [
            'sofa' => [
                'label' => 'Диван',
                'subs' => [
                    ['slug' => 'straight', 'label' => 'Прямой диван'],
                    ['slug' => 'corner', 'label' => 'Угловой диван'],
                    ['slug' => 'compact', 'label' => 'Малогабаритный диван'],
                    ['slug' => 'modular', 'label' => 'Модульный диван'],
                ],
            ],
            'armchair' => [
                'label' => 'Кресло',
                'subs' => [
                    ['slug' => 'armchair', 'label' => 'Кресло'],
                    ['slug' => 'chair-bed', 'label' => 'Кресло-кровать'],
                ],
            ],
            'combination' => [
                'label' => 'Комбинация',
                'subs' => [],
            ],
        ];

        $subcategoryMap = [];
        $sortCategory = 0;
        foreach ($defs as $slug => $def) {
            $category = CatalogCategory::find()->where(['slug' => $slug])->one();
            if ($category === null) {
                $category = new CatalogCategory([
                    'slug' => $slug,
                    'label' => $def['label'],
                    'sort_order' => $sortCategory,
                    'is_active' => true,
                ]);
                $category->save(false);
            }
            $sortCategory++;

            $sortSub = 0;
            foreach ($def['subs'] as $subDef) {
                $sub = CatalogSubcategory::find()
                    ->where(['category_id' => $category->id, 'slug' => $subDef['slug']])
                    ->one();
                if ($sub === null) {
                    $sub = new CatalogSubcategory([
                        'category_id' => $category->id,
                        'slug' => $subDef['slug'],
                        'label' => $subDef['label'],
                        'sort_order' => $sortSub,
                        'is_active' => true,
                    ]);
                    $sub->save(false);
                }
                $subcategoryMap[$sub->slug] = (int)$sub->id;
                $sortSub++;
            }
        }

        return $subcategoryMap;
    }

    private function ensureBadge(?array $badge): ?int
    {
        if ($badge === null || empty($badge['text'])) {
            return null;
        }

        $label = (string)$badge['text'];
        $variant = (string)($badge['variant'] ?? CatalogBadge::VARIANT_HIT);
        $model = CatalogBadge::find()->where(['label' => $label, 'variant' => $variant])->one();
        if ($model === null) {
            $model = new CatalogBadge([
                'slug' => $this->slugify($label, 'badge'),
                'label' => $label,
                'variant' => $variant,
                'sort_order' => (int)CatalogBadge::find()->count(),
                'is_active' => true,
            ]);
            $model->save(false);
        }

        return (int)$model->id;
    }

    private function ensureLayout(?string $slug): ?int
    {
        $slug = trim((string)$slug);
        if ($slug === '') {
            return null;
        }

        $layout = CatalogLayout::find()->where(['slug' => $slug])->one();

        return $layout ? (int)$layout->id : null;
    }

    private function findCollectionIdByName(?string $name): ?int
    {
        $name = trim((string)$name);
        if ($name === '') {
            return null;
        }

        $collection = CatalogCollection::find()->where(['name' => $name])->one();
        if ($collection === null) {
            $collection = CatalogCollection::find()->where(['like', 'name', $name])->one();
        }

        return $collection ? (int)$collection->id : null;
    }

    private function slugify(string $value, string $prefix): string
    {
        $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $value) ?? '', '-'));
        if ($slug === '') {
            $slug = $prefix . '-' . uniqid();
        }

        return substr($slug, 0, 64);
    }

    private function seedPages(bool $force = false): void
    {
        if (ContentPage::find()->exists() && !$force) {
            $added = $this->seedMissingPagesFromJson();
            if ($added === 0) {
                $this->stdout("  pages: already seeded\n");
            }

            return;
        }

        if ($force) {
            $db = Yii::$app->db;
            $db->createCommand('SET FOREIGN_KEY_CHECKS=0')->execute();
            foreach (['{{%content_blocks}}', '{{%content_pages}}'] as $table) {
                $db->createCommand()->truncateTable($table)->execute();
            }
            $db->createCommand('SET FOREIGN_KEY_CHECKS=1')->execute();
        }

        $imported = $this->importAllPagesFromJson();
        $this->stdout(sprintf("  pages: %d pages, %d blocks\n", $imported, ContentBlock::find()->count()));
    }

    private function seedMissingPagesFromJson(): int
    {
        $existing = ContentPage::find()->select('slug')->column();
        $existing = array_fill_keys($existing, true);
        $pagesDir = Yii::getAlias('@app/data/content/pages');
        $added = 0;

        foreach (glob($pagesDir . '/*.json') ?: [] as $file) {
            $slug = basename($file, '.json');
            if (isset($existing[$slug])) {
                continue;
            }

            $data = json_decode((string)file_get_contents($file), true);
            if (!is_array($data)) {
                continue;
            }

            $this->importPageFromJson($slug, $data);
            $added++;
        }

        if ($added > 0) {
            $this->stdout(sprintf("  pages: added %d missing page(s)\n", $added));
        }

        return $added;
    }

    private function importAllPagesFromJson(): int
    {
        $pagesDir = Yii::getAlias('@app/data/content/pages');
        $imported = 0;

        foreach (glob($pagesDir . '/*.json') ?: [] as $file) {
            $slug = basename($file, '.json');
            $data = json_decode((string)file_get_contents($file), true);
            if (!is_array($data)) {
                continue;
            }

            $this->importPageFromJson($slug, $data);
            $imported++;
        }

        return $imported;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function importPageFromJson(string $slug, array $data): void
    {
        $page = new ContentPage([
            'slug' => $slug,
            'title' => ContentPage::titleForSlug($slug),
            'is_active' => true,
        ]);
        $page->save(false);

        $sortOrder = 0;
        foreach ($data as $blockKey => $blockValue) {
            $block = new ContentBlock([
                'page_id' => (int)$page->id,
                'block_key' => (string)$blockKey,
                'block_type' => BlockTypeRegistry::detect($slug, (string)$blockKey, $blockValue),
                'data' => json_encode($blockValue, JSON_UNESCAPED_UNICODE),
                'sort_order' => $sortOrder++,
                'is_active' => true,
            ]);
            $block->save(false);
        }
    }

    private function seedSearch(bool $force = false): void
    {
        if (SearchFrequentQuery::find()->exists() && !$force) {
            $this->stdout("  search: already seeded\n");

            return;
        }

        if ($force) {
            $db = Yii::$app->db;
            $db->createCommand('SET FOREIGN_KEY_CHECKS=0')->execute();
            foreach (['{{%search_frequent_queries}}', '{{%search_categories}}', '{{%search_recommended_products}}'] as $table) {
                $db->createCommand()->truncateTable($table)->execute();
            }
            $db->createCommand('SET FOREIGN_KEY_CHECKS=1')->execute();
        }

        $path = Yii::getAlias('@app/data/content/search/bootstrap.json');
        $data = json_decode((string)file_get_contents($path), true);
        if (!is_array($data)) {
            $this->stderr("  search: invalid bootstrap.json\n", Console::FG_RED);

            return;
        }

        $sort = 0;
        foreach ($data['frequent'] ?? [] as $query) {
            $model = new SearchFrequentQuery([
                'query' => (string)$query,
                'sort_order' => $sort++,
                'is_active' => true,
            ]);
            $model->save(false);
        }

        $sort = 0;
        foreach ($data['categories'] ?? [] as $category) {
            $model = new SearchCategory([
                'slug' => $category['id'] ?? ('cat-' . $sort),
                'label' => $category['label'] ?? '',
                'icon' => $category['icon'] ?? 'search-default',
                'href' => $category['href'] ?? '',
                'sort_order' => $sort++,
                'is_active' => true,
            ]);
            $model->save(false);
        }

        $slot = 0;
        foreach ($data['recommended'] ?? [] as $item) {
            if ($slot >= SearchRecommendedProduct::MAIN_SLOT_COUNT) {
                break;
            }

            $slug = $item['id'] ?? null;
            if ($slug === null) {
                continue;
            }

            $product = CatalogProduct::find()->where(['slug' => $slug])->one();
            if ($product === null) {
                continue;
            }

            $model = new SearchRecommendedProduct([
                'scope_type' => SearchRecommendedProduct::SCOPE_MAIN,
                'scope_id' => 0,
                'slot' => $slot,
                'catalog_product_id' => (int)$product->id,
            ]);
            $model->save(false);
            $slot++;
        }

        $this->stdout(sprintf(
            "  search: %d queries, %d categories\n",
            SearchFrequentQuery::find()->count(),
            SearchCategory::find()->count()
        ));
    }
}
