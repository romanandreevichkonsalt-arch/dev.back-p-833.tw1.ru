<?php

namespace app\controllers\api\v1;

use app\exceptions\ProfileIncompleteException;
use app\models\User;
use app\services\catalog\CatalogLibraryFabricsService;
use app\services\catalog\CatalogMenuPathResolver;
use app\services\catalog\CatalogModel3dFileUploadService;
use app\services\catalog\CatalogProductDetailService;
use app\services\catalog\CatalogProductListingService;
use app\services\catalog\CatalogUnifiedService;
use app\services\dealer\DealerActivityLogger;
use OpenApi\Annotations as OA;
use Yii;
use yii\web\Response;
use yii\web\UploadedFile;

class CatalogController extends ApiController
{
    public const HEADER_TOTAL_COUNT = 'X-Total-Count';
    public const HEADER_PAGE = 'X-Page';
    public const HEADER_PER_PAGE = 'X-Per-Page';
    public const HEADER_SORT = 'X-Sort';
    public const HEADER_SCOPE_MODE = 'X-Scope-Mode';

    private CatalogUnifiedService $catalogUnified;
    private CatalogProductListingService $productListing;
    private CatalogProductDetailService $productDetail;
    private CatalogMenuPathResolver $menuPathResolver;
    private DealerActivityLogger $activityLogger;
    private CatalogModel3dFileUploadService $model3dFileUpload;
    private CatalogLibraryFabricsService $libraryFabrics;

    public function init(): void
    {
        parent::init();
        $this->catalogUnified = \Yii::$container->get(CatalogUnifiedService::class);
        $this->productListing = \Yii::$container->get(CatalogProductListingService::class);
        $this->productDetail = \Yii::$container->get(CatalogProductDetailService::class);
        $this->menuPathResolver = \Yii::$container->get(CatalogMenuPathResolver::class);
        $this->activityLogger = new DealerActivityLogger();
        $this->model3dFileUpload = new CatalogModel3dFileUploadService();
        $this->libraryFabrics = \Yii::$container->get(CatalogLibraryFabricsService::class);
    }

    public function behaviors(): array
    {
        $behaviors = parent::behaviors();
        $behaviors['authenticator']['except'] = [
            'menu',
            'menu-products',
            'navigation',
            'products',
            'library-products',
            'library-fabrics',
            'product',
            'filters',
            'upload-model-3d-file',
            'options',
        ];

        return $behaviors;
    }

    public function verbs(): array
    {
        return [
            'menu' => ['GET', 'HEAD', 'OPTIONS'],
            'menu-products' => ['GET', 'HEAD', 'OPTIONS'],
            'navigation' => ['GET', 'HEAD', 'OPTIONS'],
            'products' => ['GET', 'HEAD', 'OPTIONS'],
            'library-products' => ['GET', 'HEAD', 'OPTIONS'],
            'library-fabrics' => ['GET', 'HEAD', 'OPTIONS'],
            'product' => ['GET', 'OPTIONS'],
            'filters' => ['GET', 'HEAD', 'OPTIONS'],
            'upload-model-3d-file' => ['POST', 'OPTIONS'],
        ];
    }

    /**
     * @OA\Head(
     *     path="/api/v1/catalog/menu",
     *     tags={"Каталог"},
     *     summary="Количество товаров по scope и фильтрам",
     *     description="Лёгкий запрос без тела ответа: total и параметры листинга в заголовках. collection=true — X-Total-Count = число линеек; иначе SKU. Те же query, что у GET /catalog/menu.",
     *     @OA\Parameter(name="collection", in="query", required=false, description="true/false — группировка по линейкам; slug — scope (direction или modelLine)", @OA\Schema(oneOf={@OA\Schema(type="boolean"), @OA\Schema(type="string", example="a-plus")})),
     *     @OA\Parameter(name="itemsPerGroup", in="query", required=false, description="При collection=true: SKU на линейку (default 3, max 12)", @OA\Schema(type="integer", default=3, minimum=1, maximum=12)),
     *     @OA\Parameter(name="category", in="query", required=false, @OA\Schema(type="string", example="divany")),
     *     @OA\Parameter(name="subcategory", in="query", required=false, @OA\Schema(type="string", example="pryamye")),
     *     @OA\Parameter(name="priceMin", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="priceMax", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="color", in="query", required=false, @OA\Schema(type="array", @OA\Items(type="string"))),
     *     @OA\Parameter(name="texture", in="query", required=false, @OA\Schema(type="array", @OA\Items(type="string"))),
     *     @OA\Parameter(name="widthMin", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="widthMax", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="heightMin", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="heightMax", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="depthMin", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="depthMax", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="sleepingPlace", in="query", required=false, description="1 — только товары со спальным местом", @OA\Schema(type="integer", enum={0, 1})),
     *     @OA\Parameter(name="foldable", in="query", required=false, description="1 — только раскладные кресла", @OA\Schema(type="integer", enum={0, 1})),
     *     @OA\Parameter(name="sort", in="query", required=false, @OA\Schema(ref="#/components/schemas/CatalogListingSortQuery")),
     *     @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="perPage", in="query", required=false, @OA\Schema(type="integer", default=24)),
     *     @OA\Response(
     *         response=200,
     *         description="Пустое тело; meta листинга в заголовках",
     *         @OA\Header(header="X-Total-Count", description="collection=true — число линеек с товарами; иначе число SKU", @OA\Schema(type="integer", example=42)),
     *         @OA\Header(header="X-Page", description="Текущая страница из query", @OA\Schema(type="integer", example=1)),
     *         @OA\Header(header="X-Per-Page", description="Размер страницы из query", @OA\Schema(type="integer", example=24)),
     *         @OA\Header(header="X-Sort", description="Применённая сортировка", @OA\Schema(type="string", example="default")),
     *         @OA\Header(header="X-Scope-Mode", description="all | shortcut | chain", @OA\Schema(type="string", example="chain"))
     *     )
     * )
     */
    /**
     * @OA\Get(
     *     path="/api/v1/catalog/menu",
     *     tags={"Каталог"},
     *     summary="Каталог: меню, навигация, товары и filters",
     *     description="Единый эндпoинт каталога. Без параметров — меню (directions, collections) и корень навигации. При scope — товары, filters, sort, breadcrumb. collection=true — items[] секциями линеек (CatalogModelLineGroup), meta.layout=collectionGroups, пагинация по линейкам; collection=false или slug — плоский items[] (CatalogProductCard). Path: GET /catalog/menu/{slugs}. Алиасы: /menu/products, /navigation, /products. HEAD /catalog/menu — total в заголовках. sort=default — round-robin по model_id. Кастом-SKU скрыты. Bearer дилера — retailPrice (розница) и dealerPrice (скидка/акция).",
     *     @OA\Parameter(name="direction", in="query", required=false, description="slug направления (А+, Линия 1)", @OA\Schema(type="string", example="a-plus")),
     *     @OA\Parameter(name="collection", in="query", required=false, description="true/false — группировка по линейкам; slug — scope (direction или modelLine)", @OA\Schema(oneOf={@OA\Schema(type="boolean"), @OA\Schema(type="string", example="a-plus")})),
     *     @OA\Parameter(name="itemsPerGroup", in="query", required=false, description="При collection=true: SKU на линейку (default 3, max 12)", @OA\Schema(type="integer", default=3, minimum=1, maximum=12)),
     *     @OA\Parameter(name="category", in="query", required=false, description="slug категории", @OA\Schema(type="string", example="divany")),
     *     @OA\Parameter(name="subcategory", in="query", required=false, description="slug подкатегории — достаточен один scope-параметр для выдачи товаров", @OA\Schema(type="string", example="pryamye")),
     *     @OA\Parameter(name="priceMin", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="priceMax", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="color", in="query", required=false, @OA\Schema(type="array", @OA\Items(type="string"))),
     *     @OA\Parameter(name="texture", in="query", required=false, @OA\Schema(type="array", @OA\Items(type="string"))),
     *     @OA\Parameter(name="widthMin", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="widthMax", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="heightMin", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="heightMax", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="depthMin", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="depthMax", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="sleepingPlace", in="query", required=false, description="1 — только товары со спальным местом", @OA\Schema(type="integer", enum={0, 1})),
     *     @OA\Parameter(name="foldable", in="query", required=false, description="1 — только раскладные кресла", @OA\Schema(type="integer", enum={0, 1})),
     *     @OA\Parameter(name="sort", in="query", required=false, @OA\Schema(ref="#/components/schemas/CatalogListingSortQuery")),
     *     @OA\Parameter(name="page", in="query", required=false, description="Номер страницы (default 1)", @OA\Schema(type="integer", default=1, minimum=1)),
     *     @OA\Parameter(name="perPage", in="query", required=false, description="SKU на странице (default 24, max 100)", @OA\Schema(type="integer", default=24, minimum=1, maximum=100)),
     *     @OA\Response(
     *         response=200,
     *         description="Меню + навигация + (при scope) товары и filters",
     *         @OA\JsonContent(ref="#/components/schemas/CatalogUnifiedResponse")
     *     )
     * )
     */
    /**
     * @OA\Head(
     *     path="/api/v1/catalog/menu/{slugs}",
     *     tags={"Каталог"},
     *     summary="Количество товаров по slug в пути",
     *     description="Как HEAD /catalog/menu, но scope из path. Query collection=true/itemsPerGroup и фильтры — те же.",
     *     @OA\Parameter(name="slugs", in="path", required=true, description="Например pryamoy-divan, artemida, a-plus/divany/pryamye", @OA\Schema(type="string", example="pryamoy-divan")),
     *     @OA\Parameter(name="collection", in="query", required=false, description="true/false или slug scope", @OA\Schema(oneOf={@OA\Schema(type="boolean"), @OA\Schema(type="string")})),
     *     @OA\Parameter(name="itemsPerGroup", in="query", required=false, @OA\Schema(type="integer", default=3, minimum=1, maximum=12)),
     *     @OA\Response(response=200, description="Пустое тело; meta в заголовках (X-Total-Count: линеек при collection=true, иначе SKU)")
     * )
     */
    /**
     * @OA\Get(
     *     path="/api/v1/catalog/menu/{slugs}",
     *     tags={"Каталог"},
     *     summary="Каталог по slug в пути",
     *     description="Товары по линейке, категории, подкатегории или направлению. Цепочки: /menu/artemida/pryamye, /menu/a-plus/divany/pryamye. collection=true — items[] секциями линеек. Фильтры и sort — как у GET /catalog/menu.",
     *     @OA\Parameter(name="slugs", in="path", required=true, @OA\Schema(type="string", example="pryamoy-divan")),
     *     @OA\Parameter(name="collection", in="query", required=false, description="true/false — группировка; slug — доп. scope", @OA\Schema(oneOf={@OA\Schema(type="boolean"), @OA\Schema(type="string")})),
     *     @OA\Parameter(name="itemsPerGroup", in="query", required=false, description="SKU на линейку при collection=true", @OA\Schema(type="integer", default=3, minimum=1, maximum=12)),
     *     @OA\Parameter(name="sort", in="query", required=false, @OA\Schema(ref="#/components/schemas/CatalogListingSortQuery")),
     *     @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="perPage", in="query", required=false, @OA\Schema(type="integer", default=24)),
     *     @OA\Response(
     *         response=200,
     *         description="Меню + навигация + товары и filters",
     *         @OA\JsonContent(ref="#/components/schemas/CatalogUnifiedResponse")
     *     ),
     *     @OA\Response(response=404, description="Slug не найден")
     * )
     */
    public function actionMenu(?string $slugs = null): array|string|null
    {
        return $this->getCatalogResponse($slugs);
    }

    /** @deprecated alias — см. GET /api/v1/catalog/menu */
    public function actionMenuProducts(): array|string|null
    {
        return $this->getCatalogResponse();
    }

    /** @deprecated alias — см. GET /api/v1/catalog/menu */
    public function actionNavigation(): array|string|null
    {
        return $this->getCatalogResponse();
    }

    /** @deprecated alias — GET /api/v1/catalog/products: все товары; scope/slug — фильтр */
    /**
     * @OA\Head(
     *     path="/api/v1/catalog/products",
     *     tags={"Каталог"},
     *     summary="Количество товаров (листинг /products)",
     *     description="Как HEAD /catalog/menu: пустое тело, meta листинга в заголовках. collection=true — X-Total-Count = число линеек; иначе SKU. Те же query: scope, фильтры, sort, page, perPage, itemsPerGroup.",
     *     @OA\Parameter(name="direction", in="query", required=false, @OA\Schema(type="string", example="a-plus")),
     *     @OA\Parameter(name="collection", in="query", required=false, description="true/false или slug scope", @OA\Schema(oneOf={@OA\Schema(type="boolean"), @OA\Schema(type="string", example="a-plus")})),
     *     @OA\Parameter(name="itemsPerGroup", in="query", required=false, @OA\Schema(type="integer", default=3, minimum=1, maximum=12)),
     *     @OA\Parameter(name="modelLine", in="query", required=false, @OA\Schema(type="string", example="artemida")),
     *     @OA\Parameter(name="category", in="query", required=false, @OA\Schema(type="string", example="divany")),
     *     @OA\Parameter(name="subcategory", in="query", required=false, @OA\Schema(type="string", example="pryamoy-divan")),
     *     @OA\Parameter(name="priceMin", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="priceMax", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="color", in="query", required=false, @OA\Schema(type="array", @OA\Items(type="string"))),
     *     @OA\Parameter(name="texture", in="query", required=false, @OA\Schema(type="array", @OA\Items(type="string"))),
     *     @OA\Parameter(name="widthMin", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="widthMax", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="heightMin", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="heightMax", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="depthMin", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="depthMax", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="sleepingPlace", in="query", required=false, @OA\Schema(type="integer", enum={0, 1})),
     *     @OA\Parameter(name="foldable", in="query", required=false, @OA\Schema(type="integer", enum={0, 1})),
     *     @OA\Parameter(name="sort", in="query", required=false, @OA\Schema(ref="#/components/schemas/CatalogListingSortQuery")),
     *     @OA\Parameter(name="page", in="query", required=false, description="Страница (default 1)", @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="perPage", in="query", required=false, description="SKU на странице (default 24, max 100)", @OA\Schema(type="integer", default=24, maximum=100)),
     *     @OA\Response(
     *         response=200,
     *         description="Пустое тело; meta листинга в заголовках",
     *         @OA\Header(header="X-Total-Count", @OA\Schema(type="integer", example=678)),
     *         @OA\Header(header="X-Page", @OA\Schema(type="integer", example=1)),
     *         @OA\Header(header="X-Per-Page", @OA\Schema(type="integer", example=24)),
     *         @OA\Header(header="X-Sort", @OA\Schema(type="string", example="default")),
     *         @OA\Header(header="X-Scope-Mode", @OA\Schema(type="string", example="all"))
     *     )
     * )
     * @OA\Get(
     *     path="/api/v1/catalog/products",
     *     tags={"Каталог"},
     *     summary="Листинг всех товаров",
     *     description="Без scope — все активные SKU каталога (meta.scopeMode=all), sort=default — round-robin по model_id. collection=true — items[] секциями линеек (Аполлон, Артемида…), meta.layout=collectionGroups, пагинация по линейкам. collection=false или slug (a-plus, artemida) — как раньше. Кастом-SKU не входят. Total без тела — HEAD /catalog/products.",
     *     @OA\Parameter(name="direction", in="query", required=false, description="slug направления (А+, Линия 1)", @OA\Schema(type="string", example="a-plus")),
     *     @OA\Parameter(name="collection", in="query", required=false, description="true/false — группировка по линейкам; slug — scope (direction или modelLine)", @OA\Schema(oneOf={@OA\Schema(type="boolean"), @OA\Schema(type="string", example="a-plus")})),
     *     @OA\Parameter(name="itemsPerGroup", in="query", required=false, description="При collection=true: SKU на линейку (default 3, max 12)", @OA\Schema(type="integer", default=3, minimum=1, maximum=12)),
     *     @OA\Parameter(name="modelLine", in="query", required=false, description="slug линейки моделей", @OA\Schema(type="string", example="artemida")),
     *     @OA\Parameter(name="category", in="query", required=false, @OA\Schema(type="string", example="divany")),
     *     @OA\Parameter(name="subcategory", in="query", required=false, @OA\Schema(type="string", example="pryamoy-divan")),
     *     @OA\Parameter(name="priceMin", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="priceMax", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="color", in="query", required=false, @OA\Schema(type="array", @OA\Items(type="string"))),
     *     @OA\Parameter(name="texture", in="query", required=false, @OA\Schema(type="array", @OA\Items(type="string"))),
     *     @OA\Parameter(name="widthMin", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="widthMax", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="heightMin", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="heightMax", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="depthMin", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="depthMax", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="sleepingPlace", in="query", required=false, description="1 — только товары со спальным местом", @OA\Schema(type="integer", enum={0, 1})),
     *     @OA\Parameter(name="foldable", in="query", required=false, description="1 — только раскладные кресла", @OA\Schema(type="integer", enum={0, 1})),
     *     @OA\Parameter(name="sort", in="query", required=false, @OA\Schema(ref="#/components/schemas/CatalogListingSortQuery")),
     *     @OA\Parameter(name="page", in="query", required=false, description="Номер страницы (default 1)", @OA\Schema(type="integer", default=1, minimum=1)),
     *     @OA\Parameter(name="perPage", in="query", required=false, description="SKU на странице (default 24, max 100)", @OA\Schema(type="integer", default=24, minimum=1, maximum=100)),
     *     @OA\Response(
     *         response=200,
     *         description="Меню + товары (все или по scope), filters и meta с пагинацией",
     *         @OA\JsonContent(ref="#/components/schemas/CatalogUnifiedResponse")
     *     )
     * )
     */
    public function actionProducts(): array|string|null
    {
        return $this->getCatalogResponse(null, true);
    }

    /**
     * @OA\Head(
     *     path="/api/v1/catalog/library-products",
     *     tags={"Каталог"},
     *     summary="Количество коллекций в листинге библиотеки 3D",
     *     description="X-Total-Count — число коллекций мебели с товарами после фильтров (одна SKU на коллекцию). Те же query category, subcategory, sort, page, perPage, что у GET.",
     *     @OA\Parameter(name="category", in="query", required=false, @OA\Schema(type="string", example="divany")),
     *     @OA\Parameter(name="subcategory", in="query", required=false, @OA\Schema(type="string", example="pryamye")),
     *     @OA\Parameter(name="sort", in="query", required=false, @OA\Schema(ref="#/components/schemas/CatalogListingSortQuery")),
     *     @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="perPage", in="query", required=false, @OA\Schema(type="integer", default=24, maximum=100)),
     *     @OA\Response(
     *         response=200,
     *         description="Пустое тело; meta в заголовках",
     *         @OA\Header(header="X-Total-Count", @OA\Schema(type="integer")),
     *         @OA\Header(header="X-Page", @OA\Schema(type="integer")),
     *         @OA\Header(header="X-Per-Page", @OA\Schema(type="integer")),
     *         @OA\Header(header="X-Sort", @OA\Schema(type="string")),
     *         @OA\Header(header="X-Scope-Mode", @OA\Schema(type="string"))
     *     )
     * )
     * @OA\Get(
     *     path="/api/v1/catalog/library-products",
     *     tags={"Каталог"},
     *     summary="Сокращённый листинг для библиотеки 3D",
     *     description="Как GET /catalog/products по sort и round-robin, но в items[] — одна SKU на коллекцию мебели (catalog_collections). Фильтры: category, subcategory (и scope direction/modelLine как в листинге). Поля элемента: direction, category, subcategory, collection, image, polygons_3d, file_3d_url, badge.",
     *     @OA\Parameter(name="direction", in="query", required=false, @OA\Schema(type="string", example="a-plus")),
     *     @OA\Parameter(name="modelLine", in="query", required=false, @OA\Schema(type="string", example="artemida")),
     *     @OA\Parameter(name="category", in="query", required=false, @OA\Schema(type="string", example="divany")),
     *     @OA\Parameter(name="subcategory", in="query", required=false, @OA\Schema(type="string", example="pryamye")),
     *     @OA\Parameter(name="sort", in="query", required=false, @OA\Schema(ref="#/components/schemas/CatalogListingSortQuery")),
     *     @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer", default=1, minimum=1)),
     *     @OA\Parameter(name="perPage", in="query", required=false, description="Коллекций на странице (default 24, max 100)", @OA\Schema(type="integer", default=24, minimum=1, maximum=100)),
     *     @OA\Response(
     *         response=200,
     *         description="Сокращённые карточки по коллекциям",
     *         @OA\JsonContent(ref="#/components/schemas/CatalogLibraryProductsResponse")
     *     )
     * )
     */
    public function actionLibraryProducts(): array|string
    {
        $params = Yii::$app->request->get();

        if (Yii::$app->request->isHead) {
            $meta = $this->productListing->countLibraryProducts($params);

            return $this->respondListingCountFromMeta($meta);
        }

        return $this->productListing->getLibraryProducts($params);
    }

    /**
     * @OA\Head(
     *     path="/api/v1/catalog/library-fabrics",
     *     tags={"Каталог"},
     *     summary="Количество тканей в листинге библиотеки",
     *     description="X-Total-Count — число активных рекомендуемых цветодизайнов после фильтров. Те же query q, texture, color, page, perPage, что у GET.",
     *     @OA\Parameter(name="q", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="texture", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="color", in="query", required=false, description="slug цвета или список через запятую", @OA\Schema(type="string")),
     *     @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="perPage", in="query", required=false, @OA\Schema(type="integer", default=24, maximum=100)),
     *     @OA\Response(
     *         response=200,
     *         description="Пустое тело; meta в заголовках",
     *         @OA\Header(header="X-Total-Count", @OA\Schema(type="integer")),
     *         @OA\Header(header="X-Page", @OA\Schema(type="integer")),
     *         @OA\Header(header="X-Per-Page", @OA\Schema(type="integer"))
     *     )
     * )
     * @OA\Get(
     *     path="/api/v1/catalog/library-fabrics",
     *     tags={"Каталог"},
     *     summary="Листинг тканей для страницы библиотеки",
     *     description="Активные рекомендуемые цветодизайны (is_recommended_fabric). В каждом item — composition и martindale коллекции. Сортировка по position_number, затем по label (api_label / design_code). В meta.texturesArchiveUrl — абсолютная ссылка на ZIP фото библиотеки, если архив уже собран. Публичный GET без авторизации.",
     *     @OA\Parameter(name="q", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="texture", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="color", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer", default=1, minimum=1)),
     *     @OA\Parameter(name="perPage", in="query", required=false, @OA\Schema(type="integer", default=24, minimum=1, maximum=100)),
     *     @OA\Response(
     *         response=200,
     *         description="Список тканей",
     *         @OA\JsonContent(ref="#/components/schemas/CatalogLibraryFabricsResponse")
     *     )
     * )
     */
    public function actionLibraryFabrics(): array|string
    {
        $params = Yii::$app->request->get();

        if (Yii::$app->request->isHead) {
            return $this->respondFabricsCountFromMeta($this->libraryFabrics->countMeta($params));
        }

        return $this->libraryFabrics->list($params);
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function respondFabricsCountFromMeta(array $meta): string
    {
        $response = Yii::$app->response;
        $response->headers->set(self::HEADER_TOTAL_COUNT, (string)(int)$meta['total']);
        $response->headers->set(self::HEADER_PAGE, (string)(int)$meta['page']);
        $response->headers->set(self::HEADER_PER_PAGE, (string)(int)$meta['perPage']);
        $response->format = Response::FORMAT_RAW;
        $response->content = '';

        return '';
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function respondListingCountFromMeta(array $meta): string
    {
        $response = Yii::$app->response;
        $response->headers->set(self::HEADER_TOTAL_COUNT, (string)(int)$meta['total']);
        $response->headers->set(self::HEADER_PAGE, (string)(int)$meta['page']);
        $response->headers->set(self::HEADER_PER_PAGE, (string)(int)$meta['perPage']);
        $response->headers->set(self::HEADER_SORT, (string)$meta['sort']);
        $response->headers->set(self::HEADER_SCOPE_MODE, (string)$meta['scopeMode']);
        $response->format = Response::FORMAT_RAW;
        $response->content = '';

        return '';
    }

    /**
     * @return array<string, mixed>|string|null
     */
    private function getCatalogResponse(?string $slugs = null, bool $alwaysIncludeListing = false): array|string|null
    {
        $params = \Yii::$app->request->get();
        unset($params['slugs'], $params['path']);
        if ($slugs !== null && trim($slugs) !== '') {
            $params = array_merge($params, $this->menuPathResolver->toParams($slugs));
        }
        $subcategory = trim((string)($params['subcategory'] ?? ''));
        if ($subcategory !== '') {
            $this->logDealerPriceView($subcategory);
        }

        if (Yii::$app->request->isHead) {
            return $this->respondListingCount($params, $alwaysIncludeListing);
        }

        return $this->catalogUnified->get($params, $this->resolveOptionalUser(), $alwaysIncludeListing);
    }

    /**
     * HEAD: total и meta листинга в заголовках, без тела.
     */
    private function respondListingCount(array $params, bool $alwaysIncludeListing = false): string
    {
        $meta = $this->catalogUnified->getListingMeta($params, $alwaysIncludeListing, $this->resolveOptionalUser());
        $response = Yii::$app->response;
        $response->headers->set(self::HEADER_TOTAL_COUNT, (string)(int)$meta['total']);
        $response->headers->set(self::HEADER_PAGE, (string)(int)$meta['page']);
        $response->headers->set(self::HEADER_PER_PAGE, (string)(int)$meta['perPage']);
        $response->headers->set(self::HEADER_SORT, (string)$meta['sort']);
        $response->headers->set(self::HEADER_SCOPE_MODE, (string)$meta['scopeMode']);
        $response->format = Response::FORMAT_RAW;
        $response->content = '';

        return '';
    }

    /**
     * @OA\Get(
     *     path="/api/v1/catalog/products/{slug}",
     *     tags={"Каталог"},
     *     summary="Карточка товара по slug",
     *     description="Детальная карточка SKU. techPhotosFolderUrl и dimensionImages — тех. фото габаритов (медиатека или ссылка на диск в админке модели). orderFormBlank — актуальный бланк заказа из админки /admin/user/index (вкладка «Дилеры»), если загружен. Блок recommended — до 8 товаров той же подкатегории в том же порядке, что sort=default в листинге (кругами по моделям). Кастом-SKU и текущий товар не входят.",
     *     @OA\Parameter(name="slug", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(
     *         response=200,
     *         description="Детальная карточка товара",
     *         @OA\JsonContent(ref="#/components/schemas/CatalogProductDetailResponse")
     *     ),
     *     @OA\Response(response=404, description="Товар не найден")
     * )
     */
    public function actionProduct(string $slug): array
    {
        return $this->productDetail->getBySlug($slug, $this->resolveOptionalUser());
    }

    /**
     * @OA\Post(
     *     path="/api/v1/catalog/models/{slug}/3d-file",
     *     tags={"Каталог"},
     *     summary="Загрузка 3D-файла модели",
     *     description="Скачивает файл по ссылке (из тела запроса или из поля «Ссылка на файл 3D» модели) и сохраняет в медиатеку, либо принимает multipart file. Не входит в ответ GET /catalog/products/{slug}.",
     *     @OA\Parameter(name="slug", in="path", required=true, description="Slug модели каталога", @OA\Schema(type="string")),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 type="object",
     *                 @OA\Property(property="url", type="string", nullable=true, description="Внешний URL для скачивания; если не передан — берётся поле «URL для загрузки» модели (до сохранения в медиатеку)")
     *             )
     *         ),
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 type="object",
     *                 @OA\Property(property="file", type="string", format="binary", description="Файл 3D (glb/gltf/fbx/obj/zip/…)"),
     *                 @OA\Property(property="url", type="string", nullable=true, description="Альтернатива файлу: скачать по URL")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Файл загружен и привязан к модели",
     *         @OA\JsonContent(ref="#/components/schemas/CatalogModel3dFileUploadResponse")
     *     ),
     *     @OA\Response(response=400, description="Нет ссылки/файла или ошибка загрузки"),
     *     @OA\Response(response=404, description="Модель не найдена")
     * )
     */
    public function actionUploadModel3dFile(string $slug): array
    {
        $model = $this->model3dFileUpload->findModelBySlug($slug);
        $uploaded = UploadedFile::getInstanceByName('file');
        if ($uploaded !== null) {
            return $this->model3dFileUpload->uploadFromFile($model, $uploaded);
        }

        $body = Yii::$app->request->getBodyParams();
        $url = isset($body['url']) ? trim((string)$body['url']) : null;
        if ($url === '') {
            $url = null;
        }

        return $this->model3dFileUpload->uploadFromUrl($model, $url);
    }

    /** @deprecated alias — см. GET /api/v1/catalog/menu с scope */
    public function actionFilters(): array|string|null
    {
        return $this->getCatalogResponse();
    }

    private function logDealerPriceView(string $subcategory): void
    {
        $identity = $this->resolveOptionalUser();
        if ($identity === null || !$identity->isDealer()) {
            return;
        }

        if (!$identity->isProfileComplete()) {
            throw new ProfileIncompleteException();
        }

        $this->activityLogger->log($identity, 'price.view', ['subcategory' => $subcategory]);
    }
}
