<?php

namespace app\controllers\api\v1;

use app\components\ApiPagination;
use app\models\Product;
use app\models\search\ProductSearch;
use app\services\ProductRelationService;
use OpenApi\Annotations as OA;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;

class ProductsController extends ApiController
{
    public function behaviors(): array
    {
        $behaviors = parent::behaviors();
        $behaviors['authenticator']['except'] = [
            'index',
            'view',
            'options',
        ];

        return $behaviors;
    }

    public function verbs(): array
    {
        return [
            'index' => ['GET', 'OPTIONS'],
            'view' => ['GET', 'OPTIONS'],
            'create' => ['POST', 'OPTIONS'],
            'update' => ['PUT', 'PATCH', 'OPTIONS'],
            'delete' => ['DELETE', 'OPTIONS'],
        ];
    }

    /**
     * @OA\Get(
     *     path="/api/v1/products",
     *     tags={"Товары"},
     *     summary="Список товаров с фильтрацией",
     *     @OA\Parameter(name="q", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="slug", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="sku", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="is_active", in="query", @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="in_stock", in="query", @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="price_from", in="query", @OA\Schema(type="number")),
     *     @OA\Parameter(name="price_to", in="query", @OA\Schema(type="number")),
     *     @OA\Parameter(name="category_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="category_ids", in="query", description="Через запятую", @OA\Schema(type="string", example="1,2")),
     *     @OA\Parameter(name="category_slug", in="query", description="Фильтр по slug категории (частичное совпадение)", @OA\Schema(type="string")),
     *     @OA\Parameter(name="seo_title", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="seo_h1", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="attrs[color]", in="query", description="Фильтр по атрибуту", @OA\Schema(type="string", example="синий")),
     *     @OA\Parameter(name="attrs[width_from]", in="query", @OA\Schema(type="number", example=200)),
     *     @OA\Parameter(name="attrs[width_to]", in="query", @OA\Schema(type="number", example=250)),
     *     @OA\Parameter(name="attrs[is_new]", in="query", @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="sort", in="query", @OA\Schema(type="string", enum={"id","name","price","created_at","stock_qty"})),
     *     @OA\Parameter(name="order", in="query", @OA\Schema(type="string", enum={"asc","desc"})),
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=20)),
     *     @OA\Response(response=200, description="Список товаров", @OA\JsonContent(ref="#/components/schemas/ProductListResponse"))
     * )
     */
    public function actionIndex(): array
    {
        $search = new ProductSearch();
        $provider = $search->search(Yii::$app->request->queryParams);
        $provider->query->with(['categories', 'productAttributes.definition', 'images']);

        return ApiPagination::format(
            $provider,
            static fn (Product $product): array => $product->toApiArray()
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/products/{id}",
     *     tags={"Товары"},
     *     summary="Карточка товара",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Товар", @OA\JsonContent(ref="#/components/schemas/Product")),
     *     @OA\Response(response=404, description="Не найден")
     * )
     */
    public function actionView(int $id): array
    {
        return $this->findProduct($id)->toApiArray();
    }

    /**
     * @OA\Post(
     *     path="/api/v1/products",
     *     tags={"Товары"},
     *     summary="Создание товара",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/ProductCreateRequest")),
     *     @OA\Response(response=201, description="Создан", @OA\JsonContent(ref="#/components/schemas/Product")),
     *     @OA\Response(response=400, description="Ошибка валидации"),
     *     @OA\Response(response=401, description="Не авторизован")
     * )
     */
    public function actionCreate(): array
    {
        $body = $this->getJsonBody();
        if (empty($body['category_ids']) || !is_array($body['category_ids'])) {
            throw new BadRequestHttpException('Поле category_ids обязательно и должно быть массивом.');
        }

        $product = new Product();
        $product->load($body, '');

        /** @var ProductRelationService $service */
        $service = Yii::createObject(ProductRelationService::class);
        $product = $service->saveProductWithRelations($product, $body);
        $product = $this->findProduct((int) $product->id);

        Yii::$app->response->statusCode = 201;

        return $product->toApiArray();
    }

    /**
     * @OA\Put(
     *     path="/api/v1/products/{id}",
     *     tags={"Товары"},
     *     summary="Обновление товара",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/ProductUpdateRequest")),
     *     @OA\Response(response=200, description="Обновлён", @OA\JsonContent(ref="#/components/schemas/Product")),
     *     @OA\Response(response=400, description="Ошибка валидации"),
     *     @OA\Response(response=401, description="Не авторизован"),
     *     @OA\Response(response=404, description="Не найден")
     * )
     *
     * @OA\Patch(
     *     path="/api/v1/products/{id}",
     *     tags={"Товары"},
     *     summary="Частичное обновление товара",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/ProductUpdateRequest")),
     *     @OA\Response(response=200, description="Обновлён", @OA\JsonContent(ref="#/components/schemas/Product"))
     * )
     */
    public function actionUpdate(int $id): array
    {
        $product = $this->findProduct($id);
        $body = $this->getJsonBody();
        $product->load($body, '');

        /** @var ProductRelationService $service */
        $service = Yii::createObject(ProductRelationService::class);
        $product = $service->saveProductWithRelations($product, $body);

        return $this->findProduct((int) $product->id)->toApiArray();
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/products/{id}",
     *     tags={"Товары"},
     *     summary="Удаление товара",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=204, description="Удалён"),
     *     @OA\Response(response=401, description="Не авторизован"),
     *     @OA\Response(response=404, description="Не найден")
     * )
     */
    public function actionDelete(int $id): void
    {
        $product = $this->findProduct($id);
        $product->delete();
        Yii::$app->response->statusCode = 204;
    }

    private function findProduct(int $id): Product
    {
        $product = Product::find()
            ->with(['categories', 'productAttributes.definition', 'images'])
            ->where(['id' => $id])
            ->one();

        if ($product === null) {
            throw new NotFoundHttpException('Товар не найден.');
        }

        return $product;
    }

    /**
     * @return array<string, mixed>
     */
    private function getJsonBody(): array
    {
        return Yii::$app->request->getBodyParams();
    }
}
