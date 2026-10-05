<?php

namespace app\controllers\api\v1;

use app\services\api\OptionalBearerUserResolver;
use app\services\search\SearchService;
use OpenApi\Annotations as OA;
use Yii;
use yii\web\BadRequestHttpException;

class SearchController extends ApiController
{
    private SearchService $search;

    public function init(): void
    {
        parent::init();
        $this->search = \Yii::$container->get(SearchService::class);
    }

    public function behaviors(): array
    {
        $behaviors = parent::behaviors();
        $behaviors['authenticator']['except'] = ['bootstrap', 'index', 'products', 'options'];

        return $behaviors;
    }

    public function verbs(): array
    {
        return [
            'bootstrap' => ['GET', 'OPTIONS'],
            'index' => ['GET', 'OPTIONS'],
            'products' => ['GET', 'OPTIONS'],
        ];
    }

    /**
     * @OA\Get(
     *     path="/api/v1/search/bootstrap",
     *     tags={"Поиск"},
     *     summary="Пустое состояние поиска",
     *     description="Данные для dropdown до ввода запроса: частые запросы и быстрые категории из админки, рекомендованные товары. Не требует авторизации.",
     *     @OA\Response(
     *         response=200,
     *         description="frequent, categories, recommended",
     *         @OA\JsonContent(ref="#/components/schemas/SearchBootstrapResponse")
     *     )
     * )
     */
    public function actionBootstrap(): array
    {
        return $this->search->getBootstrap();
    }

    /**
     * @OA\Get(
     *     path="/api/v1/search",
     *     tags={"Поиск"},
     *     summary="Autocomplete-поиск по каталогу",
     *     description="Возвращает товары, блок «Часто ищут» (подкатегории/коллекции/frequent) и «Найдено в категориях» (агрегация подкатегорий по совпавшим SKU). Минимум 3 символа в q. История запросов — только на клиенте (localStorage), в ответе не передаётся. Товары: кругами по одному SKU из каждой модели. limit default 5, max 20; иное значение — из запроса. Кастом-SKU не входят в выдачу. Пример: `GET /api/v1/search?q=диван&limit=5`.",
     *     @OA\Parameter(
     *         name="q",
     *         in="query",
     *         required=true,
     *         description="Строка поиска (мин. 3 символа)",
     *         @OA\Schema(type="string", minLength=1, example="диван")
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         required=false,
     *         description="Число товаров в products (default 5, max 20)",
     *         @OA\Schema(type="integer", minimum=1, maximum=20, default=5, example=5)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="query, correction, products, oftenSearched, categoriesFound",
     *         @OA\JsonContent(ref="#/components/schemas/SearchIndexResponse")
     *     ),
     *     @OA\Response(response=400, description="Не указан q")
     * )
     */
    public function actionIndex(): array
    {
        $query = trim((string)Yii::$app->request->get('q', ''));
        if ($query === '') {
            throw new BadRequestHttpException('Параметр q обязателен.');
        }

        $limit = (int)Yii::$app->request->get('limit', SearchService::DEFAULT_PRODUCT_LIMIT);

        return $this->search->search($query, $limit, (new OptionalBearerUserResolver())->resolveDealer());
    }

    /**
     * @OA\Get(
     *     path="/api/v1/search/products",
     *     tags={"Поиск"},
     *     summary="Страница результатов поиска с пагинацией",
     *     description="Полная выдача совпавших SKU. sort=default (или без sort) — чередование моделей как в каталоге. perPage по умолчанию 24; значение из запроса перекрывает default (max 100). Кастом-SKU не входят в выдачу.",
     *     @OA\Parameter(name="q", in="query", required=true, @OA\Schema(type="string")),
     *     @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="perPage", in="query", required=false, @OA\Schema(type="integer", default=24)),
     *     @OA\Parameter(name="sort", in="query", required=false, @OA\Schema(type="string", default="default")),
     *     @OA\Response(
     *         response=200,
     *         description="query, correction, items, meta",
     *         @OA\JsonContent(ref="#/components/schemas/SearchProductsResponse")
     *     ),
     *     @OA\Response(response=400, description="Не указан q")
     * )
     */
    public function actionProducts(): array
    {
        $query = trim((string)Yii::$app->request->get('q', ''));
        if ($query === '') {
            throw new BadRequestHttpException('Параметр q обязателен.');
        }

        $page = (int)Yii::$app->request->get('page', 1);
        $perPage = (int)Yii::$app->request->get('perPage', 24);
        $sort = trim((string)Yii::$app->request->get('sort', 'default'));

        return $this->search->searchProducts(
            $query,
            $page,
            $perPage,
            $sort,
            (new OptionalBearerUserResolver())->resolveDealer(),
        );
    }
}
